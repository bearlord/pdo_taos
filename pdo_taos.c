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

int taos_inited = 0;

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
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_NULL",  (zend_long)(TSDB_DATA_TYPE_NULL + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_BOOL",  (zend_long)(TSDB_DATA_TYPE_BOOL + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_TINYINT",  (zend_long)(TSDB_DATA_TYPE_TINYINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_SMALLINT",  (zend_long)(TSDB_DATA_TYPE_SMALLINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_INT",  (zend_long)(TSDB_DATA_TYPE_INT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_BIGINT",  (zend_long)(TSDB_DATA_TYPE_BIGINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_FLOAT",  (zend_long)(TSDB_DATA_TYPE_FLOAT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_DOUBLE",  (zend_long)(TSDB_DATA_TYPE_DOUBLE + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_BINARY",  (zend_long)(TSDB_DATA_TYPE_BINARY + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_TIMESTAMP",  (zend_long)(TSDB_DATA_TYPE_TIMESTAMP + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_NCHAR",  (zend_long)(TSDB_DATA_TYPE_NCHAR + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_UTINYINT",  (zend_long)(TSDB_DATA_TYPE_UTINYINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_USMALLINT",  (zend_long)(TSDB_DATA_TYPE_USMALLINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_UINT",  (zend_long)(TSDB_DATA_TYPE_UINT + 6000));
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_UBIGINT",  (zend_long)(TSDB_DATA_TYPE_UBIGINT + 6000));

#ifdef TSDB_DATA_TYPE_JSON
    REGISTER_PDO_CLASS_CONST_LONG("PARAM_TAOS_JSON",  (zend_long)(TSDB_DATA_TYPE_JSON + 6000));
#endif

    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MSHUTDOWN_FUNCTION
 */
PHP_MSHUTDOWN_FUNCTION(pdo_taos)
{
    if (taos_inited) {
        taos_cleanup();
        taos_inited = 0;
    }
    php_pdo_unregister_driver(&pdo_taos_driver);
    return SUCCESS;
}
/* }}} */

/* {{{ PHP_MINFO_FUNCTION
 */
PHP_MINFO_FUNCTION(pdo_taos)
{
    php_info_print_table_start();
    php_info_print_table_header(2, "PDO Driver for TDengine", "enabled");

    php_info_print_table_row(2, "TDengine(libtaos) Version ", taos_get_client_info());

    php_info_print_table_end();
}
/* }}} */