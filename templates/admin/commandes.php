<?php
/**
 * Liste des commandes.
 *
 * Par défaut les paniers abandonnés au paiement (statut « en attente ») sont
 * masqués : ce ne sont pas des commandes.
 *
 * @var array<int, array<string,mixed>> $orders
 * @var string                          $status
 * @var string                          $fulfilment
 * @var string                          $search
 */

use Bouge\Support\Money;
use Bouge\Support\Status;
?>
<header class="admin-head">
    <div>
        <h1 class="t-l">Commandes</h1>
        <p class="muted t-s">
            <?= count($orders) ?> commande<?= count($orders) > 1 ? 's' : '' ?> affichée<?= count($orders) > 1 ? 's' : '' ?><?php if ($search !== ''): ?>
                pour « <?= e($search) ?> » — <a class="link-quiet" href="/admin/commandes">tout afficher</a><?php endif; ?>.
        </p>
    </div>
    <div class="row">
        <?php /* Comme pour les produits, l'export reprend les filtres et la
                 recherche en cours : ce qu'on voit à l'écran est ce qu'on
                 télécharge. */ ?>
        <a class="btn btn--ghost"
           href="/admin/commandes/export<?= $_GET !== [] ? '?' . e(http_build_query($_GET)) : '' ?>">
            Exporter en Excel
        </a>
    </div>
</header>

<form class="admin-filters" method="get" action="/admin/commandes">
    <?php /* La recherche d'abord : c'est le geste le plus fréquent dès que la
             boutique tourne — un client appelle avec sa référence, ou son nom.
             Elle porte sur la référence, le nom, l'adresse électronique et le
             numéro de suivi, et ne masque pas les commandes impayées : c'est
             souvent celles-là qu'on cherche. */ ?>
    <div class="field field--grow">
        <label for="q">Rechercher</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>"
               placeholder="Référence, nom, adresse ou n° de suivi">
    </div>

    <div class="field">
        <label for="statut">Statut</label>
        <select id="statut" name="statut">
            <?php /* Le libellé dit ce que le filtre fait vraiment : le choix
                     par défaut masque les paniers abandonnés au moment de
                     payer, et montre tout le reste — y compris les expédiées
                     et les annulées. « Commandes payées » le laissait croire
                     à un filtre sur le seul statut « Payée ». */ ?>
            <option value="">Toutes sauf les impayées</option>
            <?php foreach (Status::orderStatuses() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="remise">Mode de remise</label>
        <select id="remise" name="remise">
            <option value="">Tous</option>
            <?php foreach (Status::fulfilments() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $fulfilment === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field field--actions">
        <button class="btn btn--ghost" type="submit">Appliquer</button>
        <?php if ($status !== '' || $fulfilment !== ''): ?>
            <a class="link-quiet t-s" href="/admin/commandes">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($orders === []): ?>
    <p class="muted" style="margin-top:2rem">Aucune commande ne correspond à ce filtre.</p>
<?php else: ?>
    <table class="admin-table admin-table--stack">
        <thead>
            <tr>
                <th scope="col">Référence</th>
                <th scope="col">Client</th>
                <th scope="col">Remise</th>
                <th scope="col">Statut</th>
                <th scope="col" class="ta-right">Articles</th>
                <th scope="col" class="ta-right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <a href="/admin/commandes/<?= (int) $order['id'] ?>"><?= e($order['reference']) ?></a>
                        <span class="t-xs muted d-block">
                            <?= e(date('d/m/Y à H\hi', strtotime((string) $order['created_at']))) ?>
                        </span>
                    </td>
                    <td class="t-s" data-label="Client">
                        <?= e($order['customer_name']) ?>
                        <span class="t-xs muted d-block"><?= e($order['email']) ?></span>
                    </td>
                    <td class="t-s" data-label="Remise"><?= e(Status::fulfilments()[$order['fulfilment']] ?? $order['fulfilment']) ?></td>
                    <td data-label="Statut">
                        <span class="pill <?= $order['status'] === Status::ORDER_PAID ? 'pill--ink' : '' ?>">
                            <?= e(Status::orderLabel((string) $order['status'])) ?>
                        </span>
                    </td>
                    <td class="ta-right nums t-s" data-label="Articles"><?= (int) $order['item_count'] ?></td>
                    <td class="ta-right nums t-s" data-label="Montant"><?= e(Money::format((int) $order['total_cents'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
