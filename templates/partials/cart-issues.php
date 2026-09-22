<?php
/**
 * Signale au client ce qui a changé dans son panier : article retiré du
 * catalogue, rupture de stock, quantité réduite.
 *
 * @var array<int, string> $issues
 */
?>
<?php if ($issues !== []): ?>
    <div class="notice notice--accent" role="status">
        <p><strong>Votre panier a été mis à jour.</strong></p>
        <ul class="muted" style="margin:.5rem 0 0;padding-left:1.1rem">
            <?php foreach ($issues as $issue): ?>
                <li><?= e($issue) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
