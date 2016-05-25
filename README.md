# Introduction

Getting up and running with HazaarMVC is really easy and is done in only a few basic steps, depending on the operating system you are working with.  I suggest Ubuntu Linux as Hazaar has been developed on Ubuntu so it will work with it.  I have made some effort to ensure that Hazaar is cross-platform compatible, particularly with Windows support, but as I do not develop under Windows daily, some bugs may arise.  If so, please create a support issue so they can be fixed.

Hazaar MVC is installed and managed with the Hazaar Tool.  A simple program that communicates with our platform server to ensure you have the latest versions of Hazaar MVC modules installed correctly for your application.

## Linux Installation

### Using Packages (Debian/Ubuntu)

A Debian package repository is available which will allow you to keep your copy of Hazaar up to date with the latest release as part of your normal package update processes.

To add the Hazaar MVC repository to your host:

```
wget -O - "http://packages.hazaarmvc.com/repo-pub.key" | sudo apt-key add -
echo "deb http://packages.hazaarmvc.com/stable /" | sudo tee /etc/apt/sources.list.d/hazaar.list
sudo apt-get update
```

Then you can simply install Hazaar by doing the following:

```
apt-get install hazaar
```

Once you have installed the Hazaar Tool you can create a new application based on the example application by running the following commands:

```
mkdir your_app_dir
cd your_app_dir
hazaar init
```

This will download the example application and it's dependencies, which is the Hazaar MVC core module.  To install other modules you can do something like:

```
hazaar install module dbi
```

This will install the DBI database access module.  To get a list of available modules run:

```
hazaar list modules
```

### Install from source

If you prefer to manually install the framework you can do so by following the below procedure.  Using GIT you can download the latest ‘stable’ branch (or master if you like to live danerously).

#### Step 1 - Download

```
> git clone -b stable git://git.funkynerd.com/hazaar/hazaar-mvc
```

Put it anywhere you want. For a production installation I suggest _/usr/share/hazaar-mvc_

#### Step 2 - Installation

Hazaar MVC can live in a central location and your applicationcs can all link to the one source tree.  This is handy for development as it makes sure all your applications are running on the same version of Hazaar MVC.

If you installed into _/usr/share/hazaar-mvc_, just do this in your application directory:

```
cd your_app_dir
mkdir library
cd library
sudo ln -s /usr/share/hazaar-mvc/Hazaar
```

You are now ready to get apache up and running.