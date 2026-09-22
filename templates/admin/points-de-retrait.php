<?php
/**
 * Points de retrait : les adresses où le client peut venir chercher sa
 * commande. Même principe que les catégories, un seul formulaire pour
 * l'ajout et la modification.
 *
 * @var array<int, array<string,mixed>> $points
 * @var array<string, string>           $errors
 * @var array<string, string>           $values
 * @var int                             $editingId
 */

use Bouge\Support\Csrf;

$editing = null;
foreach ($points as $point) {
    if ((int) $point['id'] === (int) ($_GET['modifier'] ?? 0) || (int) $point['id'] === $editingId) {
        $editing = $point;
        break;
    }
}

$resubmitted = $values !== [];

$value = static function (string $key, string $fallback = '') use ($values, $editing): string {
    if (array_key_exists($key, $values)) {
        return (string) $values[$key];
    }

    return (string) ($editing[$key] ?? $fallback);
};

// Un nouveau point est proposé aux clients par défaut : c'est la raison pour
// laquelle on le crée.
$isActive = $resubmitted
    ? array_key_exists('is_active', $values)
    : ($editing === null || (bool) $editing['is_active']);
?>
<header class="admin-head">
    <h1 class="t-l">Points de retrait</h1>
    <p class="muted t-s">
        Les adresses proposées au client qui choisit de venir chercher sa commande.
        Sans point actif, seule la livraison est possible.
    </p>
</header>

<div class="admin-columns admin-columns--aside">
    <section>
        <?php if ($points === []): ?>
            <p class="muted">Aucun point de retrait. Ajoutez-en un pour proposer le retrait sur place.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Nom et adresse</th>
                        <th scope="col">Proposé</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($points as $point): ?>
                        <tr>
                            <td>
                                <strong><?= e($point['name']) ?></strong>
                                <span class="t-xs muted d-block">
                                    <?= e($point['address_line1']) ?>
                                    <?php if (!empty($point['address_line2'])): ?>
                                        , <?= e($point['address_line2']) ?>
                                    <?php endif; ?>
                                    — <?= e($point['postal_code']) ?> <?= e($point['city']) ?>
                                </span>
                                <?php if (!empty($point['hours'])): ?>
                                    <span class="t-xs muted d-block"><?= e($point['hours']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="pill <?= (int) $point['is_active'] === 1 ? 'pill--ink' : '' ?>">
                                    <?= (int) $point['is_active'] === 1 ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td class="ta-right">
                                <a class="link-quiet t-s" href="/admin/points-de-retrait?modifier=<?= (int) $point['id'] ?>#formulaire">
                                    Modifier
                                </a>

                                <?php if ((int) $point['order_count'] === 0): ?>
                                    <details class="confirm confirm--inline">
                                        <summary class="link-danger t-s">Supprimer</summary>
                                        <form method="post" action="/admin/points-de-retrait/supprimer">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $point['id'] ?>">
                                            <p class="t-xs">Supprimer « <?= e($point['name']) ?> » ?</p>
                                            <button class="btn btn--danger btn--sm" type="submit">Oui, supprimer</button>
                                        </form>
                                    </details>
                                <?php else: ?>
                                    <?php /* Des commandes y renvoient : le supprimer effacerait
                                             l'adresse où le client doit se présenter. */ ?>
                                    <span class="t-xs muted"><?= (int) $point['order_count'] ?> commande(s)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <aside class="admin-card" id="formulaire">
        <h2 class="t-m"><?= $editing === null ? 'Nouveau point de retrait' : 'Modifier le point' ?></h2>

        <form method="post" action="/admin/points-de-retrait/enregistrer" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $editing === null ? 0 : (int) $editing['id'] ?>">

            <div class="field">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" value="<?= e($value('name')) ?>"
                       maxlength="120" placeholder="Le concept store BOUGE" required>
                <?php if (isset($errors['name'])): ?>
                    <p class="field-error"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="address_line1">Adresse</label>
                <input type="text" id="address_line1" name="address_line1"
                       value="<?= e($value('address_line1')) ?>" maxlength="200" required>
                <?php if (isset($errors['address_line1'])): ?>
                    <p class="field-error"><?= e($errors['address_line1']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="address_line2">Complément (facultatif)</label>
                <input type="text" id="address_line2" name="address_line2"
                       value="<?= e($value('address_line2')) ?>" maxlength="200">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="postal_code">Code postal</label>
                    <input type="text" id="postal_code" name="postal_code" inputmode="numeric"
                           value="<?= e($value('postal_code')) ?>" maxlength="5" required>
                    <?php if (isset($errors['postal_code'])): ?>
                        <p class="field-error"><?= e($errors['postal_code']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="city">Ville</label>
                    <input type="text" id="city" name="city" value="<?= e($value('city')) ?>"
                           maxlength="120" required>
                    <?php if (isset($errors['city'])): ?>
                        <p class="field-error"><?= e($errors['city']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field">
                <label for="hours">Horaires (facultatif)</label>
                <input type="text" id="hours" name="hours" value="<?= e($value('hours')) ?>"
                       maxlength="200" placeholder="Du mardi au samedi, 10h–19h">
                <p class="field-help">Affichés tels quels au client au moment de choisir.</p>
                <?php if (isset($errors['hours'])): ?>
                    <p class="field-error"><?= e($errors['hours']) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="position">Ordre d'affichage</label>
                <input type="number" id="position" name="position" step="1"
                       value="<?= e($value('position', '0')) ?>">
                <p class="field-help">Le plus petit nombre apparaît en premier.</p>
            </div>

            <label class="check">
                <input type="checkbox" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
                <span>Proposé aux clients</span>
            </label>

            <div class="admin-actions">
                <button class="btn" type="submit">
                    <?= $editing === null ? 'Ajouter' : 'Enregistrer' ?>
                </button>
                <?php if ($editing !== null): ?>
                    <a class="link-quiet" href="/admin/points-de-retrait">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>
