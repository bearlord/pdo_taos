/* pdo_taos extension for PHP (c) 2021 bearlord 565364226@qq.com */
#ifndef PHP_PDO_TAOS_H
# define PHP_PDO_TAOS_H

extern zend_module_entry pdo_taos_module_entry;
extern int taos_inited;
# define phpext_pdo_taos_ptr &pdo_taos_module_entry

# define PHP_PDO_TAOS_VERSION "0.2.1"

# if defined(ZTS) && defined(COMPILE_DL_PDO_TAOS)
ZEND_TSRMLS_CACHE_EXTERN()
# endif

#ifdef PHP_WIN32
#define PHP_PDO_TAOS_API __declspec(dllexport)
#else
#define PHP_PDO_TAOS_API
#endif

#ifdef ZTS
#include "TSRM.h"
#endif

PHP_MINIT_FUNCTION(pdo_taos);
PHP_MSHUTDOWN_FUNCTION(pdo_taos);
PHP_RINIT_FUNCTION(pdo_taos);
PHP_RSHUTDOWN_FUNCTION(pdo_taos);
PHP_MINFO_FUNCTION(pdo_taos);


#endif	/* PHP_PDO_TAOS_H */
