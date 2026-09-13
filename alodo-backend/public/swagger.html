<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ALODO — Documentation API</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" crossorigin="anonymous">
    <style>
        body { margin: 0; background: #fafafa; }
        header { padding: 20px; font: 16px/1.5 system-ui, sans-serif; }
        header h1 { margin: 0 0 8px; font-size: 24px; }
        header p { margin: 8px 0; }
        #documentation-error { color: #a32121; }
    </style>
</head>
<body>
    <header>
        <h1>ALODO — Documentation des contrôleurs</h1>
        <p>Connectez-vous avec <strong>POST /api/login</strong>, puis collez <strong>data.token</strong> dans <strong>Authorize</strong>, sans le préfixe Bearer.</p>
        <p>Les boutons « Try it out » exécutent de vrais appels sur ce backend. Utilisez les identifiants et options renvoyés par votre questionnaire.</p>
        <a href="./openapi.json">Télécharger la spécification OpenAPI</a>
        <p id="documentation-error" role="alert" hidden></p>
    </header>
    <div id="swagger-ui"></div>
    <noscript>Activez JavaScript pour consulter Swagger UI, ou téléchargez la spécification JSON.</noscript>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js" crossorigin="anonymous"></script>
    <script>
        async function initializeDocumentation() {
            try {
                if (typeof SwaggerUIBundle !== 'function') {
                    throw new Error('Swagger UI n’a pas pu être chargé depuis le CDN. Vérifiez votre connexion Internet.');
                }

                const response = await fetch('./openapi.json');
                if (!response.ok) {
                    throw new Error('La spécification OpenAPI est indisponible.');
                }

                const spec = await response.json();
                // Conserve le même backend même si l’application est servie dans un sous-répertoire.
                spec.servers = [{ url: new URL('./', window.location.href).href, description: 'Backend courant' }];

                window.ui = SwaggerUIBundle({
                    spec,
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    displayRequestDuration: true,
                    persistAuthorization: false,
                    validatorUrl: null,
                    defaultModelsExpandDepth: 0,
                    requestInterceptor(request) {
                        request.headers.Accept = 'application/json';
                        return request;
                    }
                });
            } catch (error) {
                const message = document.getElementById('documentation-error');
                message.textContent = error.message;
                message.hidden = false;
            }
        }

        initializeDocumentation();
    </script>
</body>
</html>
