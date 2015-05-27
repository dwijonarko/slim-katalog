<?php
function hash_password($password) {
    return '{SSHA}' . base64_encode(sha1( $password, TRUE ));
}

echo "{SSHA}m0074NpQtpb/SkUMNfKe5DE7/01TQV3A".PHP_EOL;
echo hash_password('10103010').PHP_EOL;