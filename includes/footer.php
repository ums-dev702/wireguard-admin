
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 loading-overlay hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-xl p-6 lg:p-8 shadow-xl">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-green-400 text-2xl mr-3 lg:mr-4"></i>
                <span class="text-lg font-medium text-white">Updating...</span>
            </div>
        </div>
    </div>

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

        // Auto-refresh stats every 30 seconds
        setInterval(refreshStats, 30000);

        function refreshStats() {
            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');

            // Simulate API call - replace with actual AJAX call
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Initialize on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded successfully');
        });
    </script>
</body>

</html>