</div> <!-- End Main Content -->
</div> <!-- End d-flex wrapper -->

<script src="<?php echo APP_URL; ?>/vendor/components/jquery/jquery.min.js"></script>
<script src="<?php echo APP_URL; ?>/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="<?php echo APP_URL; ?>/vendor/select2/dist/js/select2.min.js"></script>

<!-- Script base para App global -->
<script>
    // Objeto global de la aplicación
    const App = {
        url: '<?php echo APP_URL; ?>',
        /**
         * Realiza peticiones AJAX usando fetch
         */
        ajax: async function(url, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: {}
            };

            if (data && (method === 'POST' || method === 'PUT')) {
                if (data instanceof FormData) {
                    options.body = data;
                } else {
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    options.body = new URLSearchParams(data).toString();
                }
            }

            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error("Error en petición AJAX:", error);
                throw error;
            }
        }
    };
</script>

<!-- Custom App JS (Si lo extraemos a un archivo luego) -->
<script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>
</body>

</html>