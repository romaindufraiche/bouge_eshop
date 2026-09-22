<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\OrderRepository;
use Bouge\Support\Auth;
use Bouge\Support\Session;
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

        return View::render('admin/commandes', [
            'title'      => 'Commandes',
            'orders'     => (new OrderRepository())->forAdmin($status, $fulfilment),
            'status'     => $status,
            'fulfilment' => $fulfilment,
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
}
