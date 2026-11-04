</main>

<footer class="site-footer">
    <div class="footer-inner">
        <span>© <?= date('Y') ?> Lumen. Built with HTML, CSS, JS, PHP &amp; MySQL.</span>
        <span class="footer-dot" aria-hidden="true"></span>
        <span>Where ideas dawn.</span>
    </div>
</footer>

<script src="js/main.js?v=<?= filemtime(__DIR__ . '/../js/main.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
