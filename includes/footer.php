</main>

<footer class="site-footer">
    <div class="footer-inner">
        <span>© <?= date('Y') ?> Lumen. Built with HTML, CSS, JS, PHP &amp; MySQL.</span>
        <span class="footer-dot" aria-hidden="true"></span>
        <span>Where ideas dawn.</span>
        <br>
        <span>Made by <a href="https://agassi-marriott-croos-mx7ojzq.gamma.site/" target="_blank" rel="noopener">AMC</a>.</span>
    </div>
</footer>

<?php if (is_logged_in()): ?>
<div class="modal-backdrop" id="logoutModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle">
        <h3 id="logoutTitle">Log out of Lumen?</h3>
        <p>You'll need to log in again to write or edit posts.</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" id="logoutCancel">Stay logged in</button>
            <a href="logout.php" class="btn btn-danger-solid" id="logoutConfirm">Log out</a>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="js/main.js?v=<?= filemtime(__DIR__ . '/../js/main.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
