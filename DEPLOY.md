# Deploying Hazaar MVC

Below are simple instructions for deploying Hazaar MVC on the Hazaar Platform.

## Create a new release

* Checkout out the *master* branch
* From the root directory execute: ```build/release.py```  This will increment the current version number for the core module and any sub modules that have changed.
* Commit thos changes to *master*.
* Checkout the *stable* branch.
* Merge changes from *master* to *stable*.
* Push to the GitLab host.  This will trigger the platform.py build script that will then move the files to their correct location.