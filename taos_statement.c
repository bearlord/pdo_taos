#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "pdo/php_pdo.h"
#include "pdo/php_pdo_driver.h"
#include "php_pdo_taos.h"
#include "php_pdo_taos_int.h"
#include "ttype.h"

#if HAVE_NETINET_IN_H
#include <netinet/in.h>
#endif

#if PHP_VERSION_ID >= 80100
#include "80100/taos_statement.c"
#elif PHP_VERSION_ID >= 80000
#include "80000/taos_statement.c"
#elif PHP_VERSION_ID >= 70300
#include "70300/taos_statement.c"
#elif PHP_VERSION_ID >= 70000
#include "70000/taos_statement.c"
#endif