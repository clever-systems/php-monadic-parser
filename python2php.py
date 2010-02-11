import types

class PhpExport:
    """ Simple class for exporting some python data to the php """    
    def export(self, export_data, add_tags=True, var_name='python_var'):
        res = self.getRepr(export_data) + ';'
        if add_tags:
            res = '<?php\n$' + var_name + ' = ' + res + '\n?>'  
        return res
    
    def getRepr(self, data):
        data_type = type(data)
        if data_type is types.ListType:
            return self.__list(data)
        elif data_type is types.StringType:
            return self.__string(data)
        elif data_type  is types.IntType:
            return self.__integer(data)
        elif data_type is types.FloatType:
            return self.__float(data)
        elif data_type is types.TupleType:
            return self.__tuple(data)
        elif data_type is types.DictType:
            return self.__dict(data)
        else:
            print 'Error: unimplemented type: %s' % type(data)
            return '"%s"' % repr(data)
        
    def __integer(self, int):
        return str(int)
    
    def __float(self, float):
        return str(float)
    
    def __list(self, list):
        res = 'array('
        for item in list:
            res += self.getRepr(item) + ','
        res += ')'
        return res
    
    def __tuple(self, tuple):
        return self.__list(tuple)
    
    def __string(self, string):
        return '"%s"' % string
    
    def __dict(self, dictionary):
        res = 'array('
        for k, v in dictionary.iteritems():
            res += self.getRepr(k)+'=>' + self.getRepr(v) + ','
        res += ')'
        return res

# Example
#export = {'a':[1,2,'text', (1.4,2)], 1:'c'}
#print PhpExport().export(export, var_name='test')