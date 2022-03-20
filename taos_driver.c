#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <stdio.h>
#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "ext/standard/php_string.h"
#include "main/php_network.h"
#include "pdo/php_pdo.h"
#include "pdo/php_pdo_driver.h"
#include "pdo/php_pdo_error.h"
#include "ext/standard/file.h"
#include "php_pdo_taos.h"
#include "php_pdo_taos_int.h"
#include "zend_exceptions.h"

#if PHP_VERSION_ID >= 80100
#include "80100/taos_driver.c"
#elif PHP_VERSION_ID >= 80000
#include "80000/taos_driver.c"
#elif PHP_VERSION_ID >= 70300
#include "70300/taos_driver.c"
#elif PHP_VERSION_ID >= 70000
#include "70000/taos_driver.c"
#endif
