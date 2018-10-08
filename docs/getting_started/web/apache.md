# Configuring Apache 2

Hazaar MVC is designed to run with Apache 2.x.

It also works with Nginx (see Configure Nginx) and it may work with other web servers but they are not tested and so are outside the scope of this document. If you have success getting Hazaar MVC working on another web server, please feel free to Contact Us.

## Document Root

If your HazaarMVCapplication is going to be the only thing running on your web server, then installation is incredibly easy. Your DocumentRoot is probably already set to/var/wwwso all you need to do is replace the directory /var/www/ with a symlink to your application public directory.

You will need to make sure that your default Apache configuration allows for overrides by making sure that

```apache
AllowOverride All
is set in your directory configuration. Such as:
DocumentRoot /var/www
<Directory /var/www/>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Order allow,deny
    allow from all
</Directory>
```

If your DocumentRoot is/var/www and your application is stored in/var/lib/myapplicationyou can run:

```shell
rm -rf /var/www
ln -s /var/lib/myapplication/public /var/www
```

## Sub-Directory

If your website hosts multiple sites in sub-directories, then all you need to do is put a symlink in your DocumentRoot path to your application public directory.

```shell
ln -s /var/lib/myappllciation/public /var/www/myapp
```

## Virtual Host

You can also install your HazaarMVCapplication as a virtual host.

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName myapp.example.com
    DocumentRoot /var/lib/myapplication/public
    <Directory /var/lib/myapplication/public></Directory>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Order allow,deny
        allow from all
    </Directory>
</VirtualHost>
```

You can use the above config by just changing the ServerName, DocumentRoot and Directory directives.

## Directory Alias

It is possible to run a HazaarMVCapplication in a server alias. However this is not recommended as it requires changes to the.htaccessfile which essentially lock-in the path of the application. This means that if you later decide to move the application you will also have to remember to update the.htaccess file to reflect the new path.

Setting up a server alias requires two steps.

### Step 1 – Add the server alias

Edit your webserver site config file. Normally this would be/etc/apache/sites-enabled/000-default. Add the following to somewhere inside the VirtualHost container.

```apache
Alias /myapp /var/lib/myapplication/public
    <Directory /var/lib/myapplication/public>
    Options Indexes FollowSymLinks MultiViews
    AllowOverride All
    Order allow,deny
    allow from all
</Directory>
```

### Step 2 – Update the .htaccess file

Edit your.htaccess file, in this case/var/lib/myapplication/public/.htaccessand add a RewriteBase directive so that it looks as follows:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine on
    RewriteBase /myapp
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
 </IfModule>
 ```

Where/myapp is the directory alias where you want your application to be accessible.
