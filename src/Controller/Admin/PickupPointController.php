<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\PickupPointRepository;
use Bouge\Support\Auth;
use Bouge\Support\Session;
use Bouge\Support\Validator;
use Bouge\Support\View;

/**
 * Points de retrait proposés au client dans le tunnel de commande :
 * la boutique, un club, une piscine partenaire.
 *
 * Sans aucun point actif, l'option « retrait sur place » est proposée mais
 * désactivée : la commande reste possible en livraison.
 */
final class PickupPointController
{
    public function index(): string
    {
        Auth::require();

        return $this->render([], []);
    }

    public function save(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new PickupPointRepository();
        $id = (int) ($_POST['id'] ?? 0);

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Donnez un nom à ce point de retrait.')
            ->maxLength('name', 120, 'Nom trop long (120 caractères maximum).')
            ->required('address_line1', "Indiquez l'adresse.")
            ->maxLength('address_line1', 200, 'Adresse trop longue (200 caractères maximum).')
            ->maxLength('address_line2', 200, 'Complément trop long (200 caractères maximum).')
            ->required('postal_code', 'Indiquez le code postal.')
            ->pattern('postal_code', '/^\d{5}$/', 'Le code postal doit comporter cinq chiffres.')
            ->required('city', 'Indiquez la ville.')
            ->maxLength('city', 120, 'Nom de ville trop long (120 caractères maximum).')
            ->maxLength('hours', 200, 'Horaires trop longs (200 caractères maximum).');

        if (!$validator->passes()) {
            http_response_code(422);

            return $this->render($validator->errors(), $validator->values(), $id);
        }

        $data = [
            'name'          => $validator->value('name'),
            'address_line1' => $validator->value('address_line1'),
            'address_line2' => $validator->value('address_line2') ?: null,
            'postal_code'   => $validator->value('postal_code'),
            'city'          => $validator->value('city'),
            'hours'         => $validator->value('hours') ?: null,
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            'position'      => (int) $validator->value('position'),
        ];

        if ($id > 0) {
            if ($repository->find($id) === null) {
                Session::flash('admin', "Ce point de retrait n'existe plus.");
                redirect('/admin/points-de-retrait');
            }

            $repository->update($id, $data);
            Session::flash('admin', "« {$data['name']} » a été mis à jour.");
        } else {
            $repository->create($data);
            Session::flash('admin', "« {$data['name']} » a été ajouté.");
        }

        redirect('/admin/points-de-retrait');

        return '';
    }

    public function delete(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new PickupPointRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $point = $repository->find($id);

        if ($point === null) {
            redirect('/admin/points-de-retrait');
        }

        if ($repository->delete($id)) {
            Session::flash('admin', "« {$point['name']} » a été supprimé.");
        } else {
            // Des commandes y renvoient : les clients doivent pouvoir
            // continuer à lire l'adresse où ils vont retirer leur colis.
            Session::flash(
                'admin',
                "« {$point['name']} » est rattaché à des commandes : décochez « proposé aux clients » plutôt que de le supprimer."
            );
        }

        redirect('/admin/points-de-retrait');

        return '';
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function render(array $errors, array $values, int $editingId = 0): string
    {
        return View::render('admin/points-de-retrait', [
            'title'     => 'Points de retrait',
            'points'    => (new PickupPointRepository())->allWithCounts(),
            'errors'    => $errors,
            'values'    => $values,
            'editingId' => $editingId,
        ], 'layout/admin');
    }
}
