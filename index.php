<?php

define('SECRETS_ROOT_DIR', __dir__);

require_once __dir__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Webmozart\PathUtil\Path;

$dotenv = new Dotenv();


// You can also load several files
$dotenv->load(
                Path::join(SECRETS_ROOT_DIR, '.config', '.env.dev')
            );

$recaptcha_sitekey = getenv('recaptcha_sitekey');

?>
<!doctype html>
<html lang="en">
<head>
    <title>Vendi Secrets Transfer</title>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $recaptcha_sitekey; ?>"></script>
    <script>
        grecaptcha
            .ready(
                () => {
                    grecaptcha
                        .execute(
                                '<?php echo $recaptcha_sitekey; ?>',
                                {action: 'action_name'}
                        )
                        .then(
                            (token) => {

                            }
                        );
                }
            )
        ;

    </script>
</head>
<body>
</body>
</html>
