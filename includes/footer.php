<script>
    // Menu functionality for mobile
    function toggleMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    function closeMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }

    function closeMenuOnMobile() {
        if (window.innerWidth < 1024) {
            closeMenu();
        }
    }

    document.getElementById('menu-toggle').addEventListener('click', toggleMenu);
</script>
</body>

</html>