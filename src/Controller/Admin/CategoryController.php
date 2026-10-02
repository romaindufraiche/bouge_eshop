<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\CategoryRepository;
use Bouge\Support\Auth;
use Bouge\Support\Uploads;
use Bouge\Support\Session;
use Bouge\Support\Validator;
use Bouge\Support\View;

final class CategoryController
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

        $id = (int) ($_POST['id'] ?? 0);

        $validator = new Validator($_POST);
        $validator
            ->required('name', 'Donnez un nom à la catégorie.')
            ->maxLength('name', 80, 'Nom trop long (80 caractères maximum).')
            ->maxLength('description', 300, 'Description trop longue (300 caractères maximum).');

        if (!$validator->passes()) {
            http_response_code(422);

            return $this->render($validator->errors(), $validator->values(), $id);
        }

        $repository = new CategoryRepository();
        $name = $validator->value('name');
        $description = $validator->value('description') ?: null;

        if ($id > 0) {
            if ($repository->find($id) === null) {
                Session::flash('admin', "Cette catégorie n'existe plus.");
                redirect('/admin/categories');
            }

            $repository->update($id, $name, $description);
            Session::flash('admin', "La catégorie « {$name} » a été mise à jour.");
        } else {
            $repository->create($name, $description);
            Session::flash('admin', "La catégorie « {$name} » a été créée.");
        }

        redirect('/admin/categories');

        return '';
    }

    /**
     * Supprime une catégorie.
     *
     * Une catégorie vide part sans question. Une catégorie pleine pose la
     * seule qui vaille : que deviennent les produits ? Les refuser purement
     * et simplement, comme avant, revenait à ne jamais pouvoir supprimer un
     * rayon — il aurait fallu vider la catégorie produit par produit, sans
     * outil pour le faire.
     */
    public function delete(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new CategoryRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $category = $repository->find($id);

        if ($category === null) {
            Session::flash('admin', "Cette catégorie n'existe plus.");
            redirect('/admin/categories');
        }

        $nom = (string) $category['name'];

        if ($repository->productCount($id) === 0) {
            $repository->delete($id);
            Session::flash('admin', "La catégorie « {$nom} » a été supprimée.");
            redirect('/admin/categories');
        }

        if (($_POST['produits'] ?? '') === 'supprimer') {
            // Les fichiers avant les lignes : en cas d'incident, on préfère
            // une image orpheline sur le disque à une fiche qui pointe vers
            // du vide.
            foreach ($repository->productImageUrls($id) as $url) {
                Uploads::delete($url);
            }

            $supprimes = $repository->deleteWithProducts($id);
            Session::flash(
                'admin',
                "« {$nom} » a été supprimée avec ses {$supprimes} produit"
                . ($supprimes > 1 ? 's' : '') . '.'
            );
            redirect('/admin/categories');
        }

        $destination = (int) ($_POST['destination'] ?? 0);

        // La destination doit exister et ne pas être la catégorie qu'on
        // supprime : un formulaire bricolé ne doit pas pouvoir y déplacer les
        // produits juste avant de l'effacer.
        if ($destination === $id || $repository->find($destination) === null) {
            Session::flash('admin', "Choisissez la catégorie qui accueillera les produits de « {$nom} ».");
            redirect('/admin/categories');
        }

        $vers = (string) $repository->find($destination)['name'];
        $deplaces = $repository->deleteMovingProductsTo($id, $destination);

        Session::flash(
            'admin',
            "« {$nom} » a été supprimée ; ses {$deplaces} produit" . ($deplaces > 1 ? 's sont' : ' est')
            . " maintenant dans « {$vers} »."
        );

        redirect('/admin/categories');

        return '';
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    private function render(array $errors, array $values, int $editingId = 0): string
    {
        return View::render('admin/categories', [
            'title'      => 'Catégories',
            'categories' => (new CategoryRepository())->allWithCounts(),
            'errors'     => $errors,
            'values'     => $values,
            'editingId'  => $editingId,
        ], 'layout/admin');
    }
}
