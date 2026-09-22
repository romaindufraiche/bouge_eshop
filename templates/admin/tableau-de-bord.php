<?php
/**
 * Tableau de bord : ce qu'il y a à faire aujourd'hui, en un coup d'œil.
 *
 * @var int                            $published
 * @var int                            $drafts
 * @var int                            $categories
 * @var int                            $toPrepare
 * @var int                            $paidTotal
 * @var array<int, array<string,mixed>> $recent
 * @var array<int, array<string,mixed>> $lowStock
 */

use Bouge\Support\Money;
use Bouge\Support\Status;
?>
<header class="admin-head">
    <h1 class="t-l">Tableau de bord</h1>
    <p class="muted t-s">Bonjour. Voici l'état de la boutique.</p>
</header>

<div class="admin-stats">
    <a class="admin-stat" href="/admin/commandes">
        <span class="eyebrow">Commandes à préparer</span>
        <strong class="nums"><?= (int) $toPrepare ?></strong>
    </a>
    <div class="admin-stat">
        <span class="eyebrow">Total encaissé</span>
        <strong class="nums"><?= e(Money::format($paidTotal)) ?></strong>
    </div>
    <a class="admin-stat" href="/admin/produits?statut=<?= e(Status::PRODUCT_PUBLISHED) ?>">
        <span class="eyebrow">Produits en ligne</span>
        <strong class="nums"><?= (int) $published ?></strong>
    </a>
    <a class="admin-stat" href="/admin/produits?statut=<?= e(Status::PRODUCT_DRAFT) ?>">
        <span class="eyebrow">Brouillons</span>
        <strong class="nums"><?= (int) $drafts ?></strong>
    </a>
</div>

<div class="admin-columns">
    <section>
        <h2 class="t-m">Dernières commandes</h2>

        <?php if ($recent === []): ?>
            <p class="muted t-s">Aucune commande pour le moment.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Référence</th>
                        <th scope="col">Client</th>
                        <th scope="col">Statut</th>
                        <th scope="col" class="ta-right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $order): ?>
                        <tr>
                            <td>
                                <a href="/admin/commandes/<?= (int) $order['id'] ?>">
                                    <?= e($order['reference']) ?>
                                </a>
                                <span class="t-xs muted d-block">
                                    <?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?>
                                </span>
                            </td>
                            <td><?= e($order['customer_name']) ?></td>
                            <td><span class="pill"><?= e(Status::orderLabel((string) $order['status'])) ?></span></td>
                            <td class="ta-right nums"><?= e(Money::format((int) $order['total_cents'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="t-m">Stocks à surveiller</h2>

        <?php if ($lowStock === []): ?>
            <p class="muted t-s">Tous les stocks sont confortables.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Produit</th>
                        <th scope="col" class="ta-right">Reste</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $line): ?>
                        <tr>
                            <td>
                                <a href="/admin/produits/<?= (int) $line['id'] ?>"><?= e($line['name']) ?></a>
                                <?php if (!empty($line['variant_label'])): ?>
                                    <span class="t-xs muted d-block"><?= e($line['variant_label']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ta-right nums">
                                <?php /* Zéro mis en évidence : c'est une rupture, pas un stock bas. */ ?>
                                <span class="pill <?= (int) $line['stock'] === 0 ? 'pill--accent' : '' ?>">
                                    <?= (int) $line['stock'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<p style="margin-top:2.5rem">
    <a class="btn" href="/admin/produits/nouveau">Ajouter un produit</a>
</p>
