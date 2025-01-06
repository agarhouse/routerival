</main>
    <footer class="bg-gray-800 text-white mt-8 py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-sm">&copy; <?php echo date('Y'); ?> RouteRival. All rights reserved.</p>
                </div>
                <div class="flex space-x-4">
                    <a href="privacy.php" class="text-sm text-gray-400 hover:text-white">Privacy Policy</a>
                    <a href="terms.php" class="text-sm text-gray-400 hover:text-white">Terms of Service</a>
                    <a href="contact.php" class="text-sm text-gray-400 hover:text-white">Contact Us</a>
                </div>
            </div>
            <div class="mt-4 text-center text-xs text-gray-500">
                Version 1.0.0
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script>
        // Function to show error messages in a dismissable alert
        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded flex items-center shadow-lg';
            alert.innerHTML = `
                <span class="mr-2">${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-red-700 hover:text-red-900">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            `;
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Function to show success messages
        function showSuccess(message) {
            const alert = document.createElement('div');
            alert.className = 'fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded flex items-center shadow-lg';
            alert.innerHTML = `
                <span class="mr-2">${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-green-700 hover:text-green-900">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            `;
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Function to format numbers with commas
        function formatNumber(number) {
            return new Intl.NumberFormat().format(number);
        }

        // Function to format dates
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        }

        // Function to escape HTML (prevent XSS)
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>