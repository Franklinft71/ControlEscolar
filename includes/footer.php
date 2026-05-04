</div> <!-- End Main Content -->
</div> <!-- End d-flex wrapper -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.7/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
