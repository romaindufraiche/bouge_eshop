<?php
/**
 * Espace client : commandes, suivi, coordonnées.
 *
 * @var array<string, mixed>            $client
 * @var array<int, array<string,mixed>> $commandes
 * @var int                             $panier
 */

use Bouge\Shipping\Tracking;
use Bouge\Support\Csrf;
use Bouge\Support\Money;
use Bouge\Support\Status;
?>
<div class="wrap">
    <div class="section">
        <header class="between">
            <div>
                <p class="eyebrow">Mon compte</p>
                <h1 class="t-xl" style="margin-top:.5rem">Bonjour <?= e($client['name']) ?></h1>
                <p class="muted t-s" style="margin-top:.5rem"><?= e($client['email']) ?></p>
            </div>

            <form method="post" action="/compte/deconnexion">
                <?= Csrf::field() ?>
                <button class="btn btn--ghost" type="submit">Se déconnecter</button>
            </form>
        </header>

        <?php if ($panier > 0): ?>
            <p class="notice" style="margin-top:2rem">
                <strong><?= (int) $panier ?> article<?= $panier > 1 ? 's' : '' ?> dans votre panier.</strong>
                Il vous attend, même si vous revenez d'un autre appareil.
                <a href="/panier">Le voir</a>.
            </p>
        <?php endif; ?>

        <div class="compte">
            <section>
                <h2 class="t-l">Mes commandes</h2>

                <?php if ($commandes === []): ?>
                    <div class="vide" style="margin-top:1.5rem">
                        <p class="t-m">Aucune commande pour l'instant.</p>
                        <p class="muted t-s">
                            Vos achats apparaîtront ici, avec leur suivi.
                            <a href="/boutique">Voir le catalogue</a>.
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="commandes">
                        <?php foreach ($commandes as $commande): ?>
                            <?php
                            $mode = Status::fulfilments()[$commande['fulfilment']] ?? 'Livraison';
                            $expediee = in_array($commande['status'], [Status::ORDER_SHIPPED, Status::ORDER_COLLECTED], true);
                            ?>
                            <li>
                                <a href="/compte/commande/<?= e($commande['reference']) ?>">
                                    <div class="commandes__tete">
                                        <span class="commandes__ref"><?= e($commande['reference']) ?></span>
                                        <span class="pill <?= $expediee ? 'pill--ink' : '' ?>">
                                            <?= e(Status::orderLabel((string) $commande['status'])) ?>
                                        </span>
                                    </div>

                                    <p class="t-s muted">
                                        <?= e(date('d/m/Y', strtotime((string) $commande['created_at']))) ?>
                                        · <?= (int) $commande['item_count'] ?> article<?= (int) $commande['item_count'] > 1 ? 's' : '' ?>
                                        · <?= e($mode) ?>
                                    </p>

                                    <?php if (!empty($commande['tracking_number'])): ?>
                                        <?php $lienSuivi = Tracking::lien(
                                            (string) $commande['tracking_carrier'],
                                            (string) $commande['tracking_number']
                                        ); ?>
                                        <p class="t-s">
                                            Suivi <?= e(Tracking::nom((string) $commande['tracking_carrier'])) ?>
                                            <?php if ($lienSuivi !== null): ?>
                                                <a class="nums" href="<?= e($lienSuivi) ?>" rel="noopener" target="_blank"><?= e($commande['tracking_number']) ?></a>
                                            <?php else: ?>
                                                <span class="nums"><?= e($commande['tracking_number']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="commandes__total nums"><?= e(Money::format((int) $commande['total_cents'])) ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <aside>
                <div class="admin-card" style="margin-top:0;background:var(--sand);border-color:var(--line)">
                    <h2 class="t-m">Mes coordonnées</h2>
                    <p class="field-help">
                        Elles préremplissent le tunnel de commande. Rien n'est
                        transmis à qui que ce soit.
                    </p>

                    <?php
                    // La valeur affichée : celle du compte, et à défaut celle
                    // de la dernière commande. Rien n'est enregistré tant que
                    // la personne n'a pas validé.
                    $valeur = static function (string $cle) use ($client, $suggere): string {
                        $propre = trim((string) ($client[$cle] ?? ''));

                        return $propre !== '' ? $propre : trim((string) ($suggere[$cle] ?? ''));
                    };
                    ?>

                    <?php if ($suggere !== null): ?>
                        <p class="notice t-xs" style="margin-top:1rem">
                            Préremplies avec l'adresse de votre dernière commande.
                            Vérifiez-les, puis enregistrez.
                        </p>
                    <?php endif; ?>

                    <form method="post" action="/compte/coordonnees" class="stack" style="margin-top:1.25rem">
                        <?= Csrf::field() ?>

                        <div class="field">
                            <label for="name">Nom</label>
                            <input type="text" id="name" name="name" value="<?= e($valeur('name')) ?>"
                                   maxlength="120" required>
                        </div>

                        <div class="field">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($valeur('phone')) ?>"
                                   maxlength="30">
                        </div>

                        <div class="field">
                            <label for="address_line1">Adresse</label>
                            <input type="text" id="address_line1" name="address_line1"
                                   value="<?= e($valeur('address_line1')) ?>" maxlength="200">
                        </div>

                        <div class="field">
                            <label for="address_line2">Complément</label>
                            <input type="text" id="address_line2" name="address_line2"
                                   value="<?= e($valeur('address_line2')) ?>" maxlength="200">
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="postal_code">Code postal</label>
                                <input type="text" id="postal_code" name="postal_code" inputmode="numeric"
                                       value="<?= e($valeur('postal_code')) ?>" maxlength="5">
                            </div>

                            <div class="field">
                                <label for="city">Ville</label>
                                <input type="text" id="city" name="city"
                                       value="<?= e($valeur('city')) ?>" maxlength="120">
                            </div>
                        </div>

                        <button class="btn" type="submit">Enregistrer</button>
                    </form>
                </div>
            </aside>
        </div>
    </div>
</div>
