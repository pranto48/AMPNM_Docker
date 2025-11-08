</div> <!-- Close .page-content -->
</main>
    <footer class="text-center py-4 text-slate-500 text-sm">
        <p>Copyright © <?php echo date("Y"); ?> <a href="https://itsupport.com.bd" target="_blank" class="text-cyan-400 hover:underline">IT Support BD</a>. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="assets/js/shared.js"></script>
    
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

        const page = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
        
        // Set active nav link
        const navLinks = document.querySelectorAll('#main-nav a');
        navLinks.forEach(link => {
            const linkPage = link.getAttribute('href');
            if (linkPage === page || (page === 'index.php' && linkPage === '/')) {
                link.classList.add('bg-slate-700', 'text-white');
            }
        });
    });
    </script>
</body>
</html>