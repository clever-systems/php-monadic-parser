# -*- coding: utf-8 -*-
#see http://sandersn.com/blog//index.php/2009/07/01/monadic_parsing_in_python_part_3

class Parser():
    def parse(self, s, i):
        raise NotImplementedError()
    def run(self, s):
        (s,i), answer = self.parse(s, 0)
        if i==len(s) and not isinstance(answer, Exception):
            return answer
        else:
            raise answer
def runsexp(s):
    return Bind(Chain(SkipSpace(), Sexp()),
                lambda answer: Chain(SkipSpace(), Return(answer))).run(s)


class Atom(Parser):
    def parse(self, s, i):
        if i >= len(s): return Fail("Ran off end of string").parse(s,i)
        start = i
        while i < len (s) and not s[i].isspace() and not s[i]==')' and not s[i]=='(':
            i += 1
        if i==start:
            return Fail("Atom not found at %d; '%s' found instead." % (i, s[i])).parse(s,i)
        else:
            return (s,i), s[start:i]
class SkipSpace(Parser):
    def parse(self, s, i):
        while i < len(s) and s[i].isspace():
            i += 1
        return (s,i), ()
class Char(Parser):
    def __init__(self, c):
        self.c = c
    def parse(self, s, i):
        if i >= len(s): return Fail("Ran off end of string").parse(s, i)
        if s[i]==self.c:
            return (s,i+1), self.c
        else:
            return Fail("'%s' expected at %d, but '%s' found instead. [%s]" %
                        (self.c,i,s[i],s[max(0,i-5):min(len(s), i+5)])).parse(s, i)

class Chain(Parser):
    def __init__(self, parser1, parser2):
        self.parser1 = parser1
        self.parser2 = parser2
    def parse(self, s, i):
        (s,i), a = self.parser1.parse(s, i)
        if isinstance(a, Exception): return (s,i), a
        else: return self.parser2.parse(s, i)
class Bind(Parser):
    def __init__(self, parser1, f):
        self.parser1 = parser1
        self.f = f
    def parse(self, s, i):
        (s,i), a = self.parser1.parse(s, i)
        if isinstance(a, Exception): return (s,i), a
        else: return self.f(a).parse(s, i)
class Return(Parser):
    def __init__(self, x):
        self.x = x
    def parse(self, s, i):
        return (s,i), self.x
class Fail(Parser):
    def __init__(self, msg):
        self.msg = msg
    def parse(self, s, i):
        return (s,i), Exception(self.msg)
class Or(Parser):
    def __init__(self, *alts):
        self.alts = alts
    def parse(self, s, i):
        for alt in self.alts:
            (snoo,inew), a = alt.parse(s, i)
            if not isinstance(a, Exception):
                return (snoo,inew), a
        return (snoo,inew), a # (s,i), a

