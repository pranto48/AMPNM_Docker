</div> <!-- Close .page-content -->
</main>
    <footer class="text-center py-4 text-slate-500 text-sm">
        <p>Copyright © <?php echo date("Y"); ?> <a href="https://itsupport.com.bd" target="_blank" class="text-cyan-400 hover:underline">IT Support BD</a>. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="assets/js/apiClient.js"></script>
    <!-- Removed shared.js as its functionality is now handled by React components -->
    <!-- Removed page-specific JS initializers as React Router handles rendering -->
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Notyf for toast notifications
        window.notyf = new Notyf({
            duration: 3000,
            position: { x: 'right', y: 'top' },
            types: [
                { type: 'success', backgroundColor: '#22c5e', icon: { className: 'fas fa-check-circle', tagName: 'i', color: 'white' } },
                { type: 'error', backgroundColor: '#ef4444', icon: { className: 'fas fa-times-circle', tagName: 'i', color: 'white' } },
                { type: 'info', backgroundColor: '#3b82f6', icon: { className: 'fas fa-info-circle', tagName: 'i', color: 'white' } }
            ]
        });

        // Expose user role globally for client-side checks
        window.userRole = '<?php echo $_SESSION['user_role'] ?? 'viewer'; ?>';

        // Set active nav link based on current React Router path
        const navLinks = document.querySelectorAll('#main-nav a');
        navLinks.forEach(link => {
            const linkPath = new URL(link.href).pathname;
            const currentPath = window.location.pathname;
            if (linkPath === currentPath || (currentPath === '/' && linkPath === '/')) {
                link.classList.add('bg-slate-700', 'text-white');
            } else {
                link.classList.remove('bg-slate-700', 'text-white');
            }
        });
    });
    </script>
</body>
</html>