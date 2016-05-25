#!/usr/bin/python

import subprocess
import os
import sys
import argparse

import libs

if __name__ == "__main__":

    parser = argparse.ArgumentParser(description="The Hazaar Release Tool")

    parser.add_argument('-s', metavar='source', type=str, default='current', dest='source',
                        help='The source tag to check changes from.')

    parser.add_argument('-t', default=False, action='store_true', dest='test',
                        help='Test run only.  Do not commit changes.')

    parser.add_argument('-u', choices=['major', 'minor', 'micro'], type=str, default='micro', dest='update',
                        help='The version update type. Default: micro')

    args = parser.parse_args()

    if not len(args.source) > 1:
        print("Missing source commit!")
        exit(1)

    p = subprocess.Popen('git rev-parse ' + args.source, stdout=subprocess.PIPE, stderr=subprocess.PIPE, shell=True)
    p.communicate()
    rc = p.returncode

    if rc > 0:
        print('Source commit ref not found!')
        exit(1)

    BASE_DIR = os.path.dirname(sys.argv[0])
    SOURCE_DIR = os.path.realpath(BASE_DIR + '/../')
    HAZAAR_DEF = SOURCE_DIR + '/.hazaar'

    print('BASE_DIR: ' + BASE_DIR)
    print('SOURCE_DIR: ' + SOURCE_DIR)
    print('HAZAAR_DEF: ' + HAZAAR_DEF)

    fileDiff = subprocess.check_output('git diff ' + args.source + ' --name-only', shell=True)
    files = fileDiff.strip().split("\n")

    CORE_VER = 0
    FILES = os.listdir(HAZAAR_DEF)

    changes = 0

    for f in FILES:
        module_file = HAZAAR_DEF + '/' + f
        module = libs.ModuleInfo(module_file)
        if not module.has('version'):
            continue
        if module.changed(files):
            version = libs.Version(module.get('version'))
            if args.update == 'major':
                version.parts[0] += 1
                version.parts[1] = 0
                version.parts[2] = 0
            elif args.update == 'minor':
                version.parts[1] += 1
                version.parts[2] = 0
            else:
                version.parts[2] += 1
            print("Module '" + module.get('name') + "' changed to version " + version.get())
            module.set('version', version.get())
            if args.test == False:
                f = open(module_file, 'w')
                f.write(module.write(include_files=True))
                f.close()
            changes += 1
            if module.name == 'core':
                CORE_VER = module.version

    if changes > 0:
        if CORE_VER > 0:
            print('Core version updated to: ' + CORE_VER)
            if args.test == False:
                os.system('sed -i "/define(\'HAZAAR_VERSION\'/c\define(\'HAZAAR_VERSION\', \'' +
                          CORE_VER + '\');" ' + SOURCE_DIR + '/Hazaar/Application.php')
        print('Committing module version updates')
        if args.test == False:
            os.system('git commit -a -m "Auto-increment module versions for release" -q')
        if CORE_VER > 0:
            print('Creating version tag')
            if args.test == False:
                os.system('git tag -f ' + CORE_VER)
        print('Updating source tag: ' + args.source)
        if args.test == False:
            os.system('git tag -f ' + args.source)
    else:
        print('No changes')
