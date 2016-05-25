class ModuleInfo:
    def __init__(self, file):
        self.data = {}
        self.deps = []
        self.files = []

        source = open(file)

        for line in source.readlines():
            if line.find(':') > 0:
                parts = line.split(':')
                key = parts[0].strip().lower()
                value = parts[1].strip()
                if key == 'depends':
                    deps = value.split(',')
                    for dep in deps:
                        self.deps.append(dep.split(' '))
                else:
                    self.data[key] = value
            else:
                file = line.strip()
                if len(file):
                    self.files.append(file)

    def has(self, key):
        return key in self.data

    def get(self, key):
        if not key in self.data:
            return None
        return self.data[key]

    def __getattr__(self, key):
        return self.get(key)

    def set(self, key, value):
        self.data[key] = value

    def write(self, include_files=False):
        lines = []
        for item in self.data:
            lines.append(item.capitalize() + ': ' + self.data[item])

        if len(self.deps) > 0:
            depends = []
            for item in self.deps:
                depends.append(' '.join([str(i) for i in item]))
            lines.append('Depends: ' + ', '.join([str(i) for i in depends]))

        if include_files:
            lines.append('\n')
            lines = lines + self.files

        return '\n'.join([str(i) for i in lines]) + "\n\n"

    def changed(self, files):
        changed_files = set(files).intersection(self.files)
        if len(changed_files) > 0:
            return True
        for mfile in self.files:
            for file in files:
                if file[:len(mfile)] == mfile:
                    return True
        return False
