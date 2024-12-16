# Configuring Nginx

## Pre-requisites

Nginx does not have a built-in PHP module like Apache does, so we need to install PHP-FPM.  No biggie, it's easy.  On your debian (or equivalent) system just do:

```shell
# sudo apt-get install php-fpm
```

That should be it really.  You can configure PHP-FPM just as you would any other instance of PHP by editing the php.ini file located at /etc/php{x}/fpm/php.ini.

## Setting it up

Below is a simple virtual host config for Nginx, that contains everything you need to get your site up and running.

```nginx
server {

        server_name www.yourdomain.com;
        root /usr/share/hazaar-codex/public;
        index index.php;

        client_max_body_size 20M;

        location / {
                try_files $uri $uri/ /index.php$is_args$args;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
                fastcgi_param APPLICATION_ENV production;
        }

}

```

The *client_max_body_size* directive is not required but is added here to illustrate available configuration options.