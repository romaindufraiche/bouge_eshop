<?php

declare(strict_types=1);

namespace Bouge\Controller\Admin;

use Bouge\Repository\CategoryRepository;
use Bouge\Support\Auth;
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

    public function delete(): string
    {
        Auth::require();
        Auth::requireToken();

        $repository = new CategoryRepository();
        $id = (int) ($_POST['id'] ?? 0);
        $category = $repository->find($id);

        if ($category === null) {
            redirect('/admin/categories');
        }

        // Refusé si la catégorie contient encore des produits : ils se
        // retrouveraient sans rayon.
        if ($repository->delete($id)) {
            Session::flash('admin', "La catégorie « {$category['name']} » a été supprimée.");
        } else {
            Session::flash('admin', "« {$category['name']} » contient encore des produits : déplacez-les d'abord.");
        }

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
