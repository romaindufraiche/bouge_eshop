<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\OrderRepository;
use Bouge\Shipping\CarrierException;
use Bouge\Shipping\Carriers;
use Bouge\Support\Auth;
use Bouge\Support\Database;
use Bouge\Support\Invoice;
use Bouge\Support\Mailer;
use Bouge\Support\Session;
use Bouge\Support\Shipping;
use Bouge\Support\Status;
use Bouge\Support\View;

/**
 * Suivi des commandes.
 *
 * L'administration ne crée ni ne modifie jamais le contenu d'une commande :
 * les articles, les montants et le mode de remise sont figés au paiement.
 * Seuls l'avancement et une note interne sont modifiables.
 */
final class OrderController
{
    public function index(): string
    {
        Auth::require();

        $status = (string) ($_GET['statut'] ?? '');
        $fulfilment = (string) ($_GET['remise'] ?? '');
        $search = trim((string) ($_GET['q'] ?? ''));

        return View::render('admin/commandes', [
            'title'      => 'Commandes',
            'orders'     => (new OrderRepository())->forAdmin($status, $fulfilment, $search),
            'status'     => $status,
            'fulfilment' => $fulfilment,
            'search'     => $search,
        ], 'layout/admin');
    }

    /** @param array<string, string> $params */
    public function show(array $params): string
    {
        Auth::require();

        $order = (new OrderRepository())->find((int) $params['id']);

        if ($order === null) {
            http_response_code(404);

            return View::render('admin/introuvable', [
                'title' => 'Commande introuvable',
            ], 'layout/admin');
        }

        return View::render('admin/commande-detail', [
            'title' => 'Commande ' . $order['reference'],
            'order' => $order,
            // La liste dépend du mode de remise : proposer « Expédiée » sur un
            // retrait en magasin n'aurait pas de sens.
            'statuses' => Status::orderStatusesFor((string) $order['fulfilment']),
            // De quoi décider s'il faut montrer le bouton d'achat d'étiquette,
            // et annoncer le poids avant de cliquer.
            'carrierReady' => Carriers::configured(),
            'carrierName'  => Carriers::get()->name(),
            'parcelWeight' => Shipping::parcelWeightGrams($order['items']),
        ], 'layout/admin');
    }

    public function updateStatus(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new OrderRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $order = $repository->find($id);

        if ($order === null) {
            redirect('/admin/commandes');
        }

        $status = (string) ($_POST['status'] ?? '');
        $allowed = Status::orderStatusesFor((string) $order['fulfilment']);

        if (!array_key_exists($status, $allowed)) {
            Session::flash('admin', 'Ce statut ne correspond pas à cette commande.');
            redirect('/admin/commandes/' . $id);
        }

        // Le passage à « payée » vient de Stripe, jamais d'un clic : le
        // marquer à la main laisserait croire à un encaissement qui n'a pas eu
        // lieu, et le stock ne serait pas décompté.
        if ($status === Status::ORDER_PAID && $order['paid_at'] === null) {
            Session::flash('admin', "Cette commande n'a pas été payée : seul Stripe peut la marquer comme payée.");
            redirect('/admin/commandes/' . $id);
        }

        $repository->updateStatus($id, $status);
        Session::flash('admin', 'Commande ' . $order['reference'] . ' : ' . $allowed[$status] . '.');
        redirect('/admin/commandes/' . $id);

        return '';
    }

    /** Transporteur et numéro de suivi, visibles ensuite par le client. */
    public function updateTracking(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new OrderRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $order = $repository->find($id);

        if ($order === null) {
            redirect('/admin/commandes');
        }

        $carrier = trim((string) ($_POST['tracking_carrier'] ?? ''));
        $number = trim((string) ($_POST['tracking_number'] ?? ''));

        $repository->updateTracking(
            $id,
            $carrier === '' ? null : mb_substr($carrier, 0, 60),
            $number === '' ? null : mb_substr($number, 0, 80)
        );

        $prevenu = $number === '' ? false : $this->previenirExpedition($repository, $id);

        Session::flash('admin', match (true) {
            $number === '' => 'Suivi retiré.',
            $prevenu       => "Suivi enregistré et avis d'expédition envoyé au client.",
            default        => 'Suivi enregistré : le client le voit sur sa commande.',
        });
        redirect('/admin/commandes/' . $id);

        return '';
    }

    /**
     * Achète l'étiquette auprès du transporteur.
     *
     * C'est le seul geste que le vendeur ne peut pas faire depuis le site
     * autrement : le reste — emballer, coller, déposer — se passe sur une
     * table. On enregistre l'étiquette et le suivi, et la commande passe à
     * « Expédiée ».
     */
    public function buyLabel(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new OrderRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $order = $repository->find($id);

        if ($order === null) {
            redirect('/admin/commandes');
        }

        // Une commande impayée ne part pas, et une étiquette déjà achetée est
        // déjà payée : la racheter coûterait une seconde fois.
        if ($order['paid_at'] === null) {
            Session::flash('admin', "Cette commande n'est pas payée : aucune étiquette ne peut être achetée.");
            redirect('/admin/commandes/' . $id);
        }

        if (!empty($order['label_url'])) {
            Session::flash('admin', 'Une étiquette a déjà été achetée pour cette commande.');
            redirect('/admin/commandes/' . $id);
        }

        if (!in_array((string) $order['fulfilment'], Status::shippedFulfilments(), true)) {
            Session::flash('admin', "Cette commande est à retirer sur place : elle n'a pas d'étiquette.");
            redirect('/admin/commandes/' . $id);
        }

        try {
            $label = Carriers::get()->buyLabel($order, Shipping::parcelWeightGrams($order['items']));
        } catch (CarrierException $e) {
            error_log("Achat d'étiquette impossible pour la commande {$order['reference']} : " . $e);
            Session::flash('admin', "L'étiquette n'a pas pu être achetée. " . $e->getMessage());
            redirect('/admin/commandes/' . $id);
        }

        $repository->attachLabel($id, $label);
        $prevenu = $this->previenirExpedition($repository, $id);

        Session::flash('admin', 'Étiquette achetée. Imprimez-la, collez-la sur le colis, et déposez-le.'
            . ($prevenu ? " Le client vient de recevoir son avis d'expédition." : ''));
        redirect('/admin/commandes/' . $id);

        return '';
    }

    /** La facture en PDF, telle que le client la reçoit. */
    public function invoice(array $params): string
    {
        Auth::require();

        $repository = new OrderRepository();
        $order = $repository->find((int) $params['id']);

        if ($order === null) {
            redirect('/admin/commandes');
        }

        if ($order['paid_at'] === null) {
            Session::flash('admin', "Cette commande n'est pas payée : aucune facture ne peut être émise.");
            redirect('/admin/commandes/' . (int) $params['id']);
        }

        $pdf = Invoice::render($order);
        // Relu après l'édition : c'est elle qui a pu attribuer le numéro.
        $order = $repository->find((int) $params['id']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . Invoice::filename($order) . '"');
        header('Content-Length: ' . strlen($pdf));

        return $pdf;
    }

    public function updateNote(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new OrderRepository();
        $id = (int) ($_POST['id'] ?? 0);

        if ($repository->find($id) === null) {
            redirect('/admin/commandes');
        }

        $note = trim((string) ($_POST['admin_note'] ?? ''));
        $repository->updateNote($id, $note === '' ? null : mb_substr($note, 0, 1000));

        Session::flash('admin', 'Note enregistrée.');
        redirect('/admin/commandes/' . $id);

        return '';
    }

    /**
     * Prévient le client que son colis est parti.
     *
     * Une seule fois : la date d'envoi est posée en base, et corriger un
     * numéro de suivi ne redéclenche donc pas le message. Les commandes à
     * retirer sur place n'en reçoivent pas — rien ne part.
     *
     * L'envoi est enfermé dans son propre try/catch : un serveur de courriel
     * muet ne doit pas empêcher d'enregistrer une expédition qui, elle, a bien
     * eu lieu.
     */
    private function previenirExpedition(OrderRepository $repository, int $id): bool
    {
        try {
            $order = $repository->find($id);

            if ($order === null
                || !in_array((string) $order['fulfilment'], Status::shippedFulfilments(), true)
                || $order['shipping_email_sent_at'] !== null
                || trim((string) ($order['tracking_number'] ?? '')) === ''
            ) {
                return false;
            }

            $sujet = 'Votre commande ' . $order['reference'] . ' est en route';

            if (!Mailer::send((string) $order['email'], $sujet, 'commande-expediee', ['commande' => $order])) {
                return false;
            }

            Database::run('UPDATE orders SET shipping_email_sent_at = NOW() WHERE id = ?', [$id]);

            return true;
        } catch (\Throwable $e) {
            error_log("Avis d'expédition non envoyé pour la commande {$id} : " . $e->getMessage());

            return false;
        }
    }
}
