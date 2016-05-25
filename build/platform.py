# Build script for Hazaar Platform Server (hazaar-platform)

import os
import sys
import shutil

import libs


def ksort(d):
    return [(k, d[k]) for k in sorted(d.keys())]


def deploy_module(module_file, source_dir, target_dir):
    info = libs.ModuleInfo(module_file)
    if not info.has('name'):
        print('Bad module: ' + module_file)
        return
    if not info.has('version'):
        print('Skipping ' + info.get('name') + ' due to no version')
        return
    version = libs.Version(info.get('version'))
    print("Processing: " + info.get('name') + '(' + version.get() + ')')
    for file in info.files:
        source = source_dir + '/' + file
        target = target_dir + '/' + info.get('name') + '/' + version.get() + '/' + file
        if os.path.isdir(source):
            if os.path.exists(target):
                shutil.rmtree(target)
            shutil.copytree(source_dir + '/' + file, target)
        else:
            target_module_dir = os.path.dirname(target)
            if not os.path.exists(target_module_dir):
                os.makedirs(target_module_dir)
            if os.path.exists(target):
                os.remove(target)
            shutil.copy(source, target)
        info.set('version', version.get())
    f = open(target_dir + '/' + info.get('name') + '/' + version.get() + '/.module', 'w')
    f.write(info.write())
    f.close()


if __name__ == "__main__":

    print('Python: ' + str(sys.version_info.major) + '.' + str(sys.version_info.minor) + '.' + str(
            sys.version_info.micro))

    BASE_DIR = os.path.dirname(sys.argv[0])
    SOURCE_DIR = os.path.realpath(BASE_DIR + '/../')
    HAZAAR_DEF = SOURCE_DIR + '/.hazaar'

    if not len(sys.argv) > 1:
        print("Missing target directory!")
        exit(1)

    TARGET = sys.argv[1]

    print('TARGET: ' + TARGET)
    print('BASE_DIR: ' + BASE_DIR)
    print('SOURCE_DIR: ' + SOURCE_DIR)
    print('HAZAAR_DEF: ' + HAZAAR_DEF)

    if not os.path.exists(TARGET):
        os.makedirs(TARGET)

    FILES = os.listdir(HAZAAR_DEF)

    for f in FILES:
        deploy_module(HAZAAR_DEF + '/' + f, SOURCE_DIR, TARGET)
