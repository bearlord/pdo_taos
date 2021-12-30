/* pdo_taos extension for PHP (c) 2021 bearlord 565364226@qq.com */

#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "pdo/php_pdo.h"
#include "pdo/php_pdo_driver.h"
#include "php_pdo_taos.h"
#include "php_pdo_taos_int.h"
#include <stdio.h>


/* {{{ pdo_taos_functions[] */
static const zend_function_entry pdo_taos_functions[] = {
    PHP_FE_END
};
/* }}} */

/* {{{ pdo_taos_deps
 */
static zend_module_dep pdo_taos_deps[] = {
    ZEND_MOD_REQUIRED("pdo")
    ZEND_MOD_END
};
/* }}} */

/* {{{ pdo_taos_module_entry */
zend_module_entry pdo_taos_module_entry = {
    STANDARD_MODULE_HEADER_EX, NULL,
    pdo_taos_deps,
    "pdo_taos",
    pdo_taos_functions,
    PHP_MINIT(pdo_taos),
    PHP_MSHUTDOWN(pdo_taos),
    NULL,
    NULL,
    PHP_MINFO(pdo_taos),
    PHP_PDO_TAOS_VERSION,
    STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_PDO_TAOS
ZEND_GET_MODULE(pdo_taos)
#endif

/* true global environment */

/* {{{ PHP_MINIT_FUNCTION
 */
PHP_MINIT_FUNCTION(pdo_taos)
{
    php_pdo_register_driver(&pdo_taos_driver);
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(pdo_taos)
{
    taos_cleanup();
    php_pdo_unregister_driver(&pdo_taos_driver);
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(pdo_taos)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "PDO Driver for TAOS", "enabled");

    php_info_print_table_row(2, "Module version", pdo_taos_module_entry.version);

    php_info_print_table_end();
}
/* }}} */