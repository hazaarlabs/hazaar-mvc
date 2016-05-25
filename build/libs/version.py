import re


class Version:
    def __init__(self, version):
        if version is None:
            raise Exception('Version can not be null')
        if not re.compile('[0-9]+(\\.[0-9]+)*').match(version):
            raise Exception('Invalid version format')
        self.parts = []
        parts = re.split('\\.', version)
        for i in parts:
            self.parts.append(int(i))

    def get(self):
        return '.'.join(str(c) for c in self.parts)

    def __str__(self):
        return self.get()

    def compare_to(self, that):
        if that is None:
            return 1
        if not isinstance(that, Version):
            that = Version(that)
        that_parts = re.split('\\.', that.get())
        length = max(len(self.parts), len(that_parts))
        for i in range(0, length):
            this_part = int(self.parts[i]) if i < len(self.parts) else 0
            that_part = int(that_parts[i]) if i < len(that_parts) else 0
            if this_part < that_part:
                return -1
            if this_part > that_part:
                return 1
        return 0

    def equals(self, that):
        if self == that:
            return True
        if that is None:
            return False
        if type(self) != type(that):
            return False

        return self.compare_to(that) == 0
