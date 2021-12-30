dnl config.m4 for extension pdo_taos

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary.

dnl If your extension references something external, use 'with':

dnl PHP_ARG_WITH([pdo_taos],
dnl   [for pdo_taos support],
dnl   [AS_HELP_STRING([--with-pdo_taos],
dnl     [Include pdo_taos support])])

dnl Otherwise use 'enable':

PHP_ARG_ENABLE([pdo_taos],
  [whether to enable pdo_taos support],
  [AS_HELP_STRING([--enable-pdo_taos],
    [Enable pdo_taos support])],
  [no])

if test "$PHP_PDO_TAOS" != "no"; then
  if test "$PHP_PDO" = "no" && test "$ext_shared" = "no"; then
    AC_MSG_ERROR([PDO is not enabled! Add --enable-pdo to your configure line.])
  fi

  # --with-pdo_taos -> check with-path
  SEARCH_PATH="/usr/local/taos /usr/local"
  SEARCH_FOR="/include/taos.h"
  if test -r $PHP_PDO_TAOS/$SEARCH_FOR; then # path given as parameter
    PDO_TAOS_DIR=$PHP_PDO_TAOS
  else # search default path list
    AC_MSG_CHECKING([for pdo_taos files in default path])
    for i in $SEARCH_PATH ; do
      if test -r $i/$SEARCH_FOR; then
        PDO_TAOS_DIR=$i
        AC_MSG_RESULT(found in $i)
      fi
    done
  fi

  if test -z "$PDO_TAOS_DIR"; then
    AC_MSG_RESULT([not found])
    AC_MSG_ERROR([Please reinstall the pdo_taos distribution])
  fi

  if test -z "$PHP_DEBUG"; then
    AC_ARG_ENABLE(debug, [  --enable-debug           with debugging symbols],[PHP_DEBUG=$enableval],[PHP_DEBUG=no])
  fi

  # --with-pdo_taos -> check for lib and symbol presence
  LIBNAME=taos
  LIBSYMBOL=taos_init
  TAOS_LIBDIR="driver"
  PHP_INCLUDEDIR="include"

  PHP_CHECK_LIBRARY($LIBNAME, $LIBSYMBOL,
  [
    AC_CHECK_LIB($LIBNAME, taos_init, AC_DEFINE(HAVE_TAOS_INIT, 1, [TDengine 2.0.5.1 or later]))
    AC_CHECK_LIB($LIBNAME, taos_cleanup, AC_DEFINE(HAVE_TAOS_CLEANUP, 1, [TDengine 2.0.5.1 or later]))
    AC_CHECK_LIB($LIBNAME, taos_options, AC_DEFINE(HAVE_TAOS_OPTIONS, 1, [TDengine 2.0.5.1 or later]))
    AC_CHECK_LIB($LIBNAME, taos_connect, AC_DEFINE(HAVE_TAOS_CONNECT, 1, [TDengine 2.0.5.1 or later]))
    AC_DEFINE(HAVE_TAOS_LIB, 1, [Define to 1 if you have the taos lib file.])
  ],[
    AC_MSG_ERROR([wrong taos lib version or lib not found, lib path $PDO_TAOS_DIR/$TAOS_LIBDIR])
  ],[
    -L$PDO_TAOS_DIR/$TAOS_LIBDIR -lm
  ])

  AC_MSG_RESULT(-L$PDO_TAOS_DIR/$TAOS_LIBDIR -lm)

  PHP_CHECK_PDO_INCLUDES

  # --with-pdo_taos -> add include path
  PHP_ADD_LIBRARY_WITH_PATH(taos, $PDO_TAOS_DIR/$TAOS_LIBDIR, PDO_TAOS_SHARED_LIBADD)
  PHP_SUBST(PDO_TAOS_SHARED_LIBADD)

  PHP_ADD_INCLUDE($PDO_TAOS_DIR/include)

  PHP_NEW_EXTENSION(pdo_taos, pdo_taos.c taos_driver.c taos_statement.c, $ext_shared,, -I$pdo_cv_inc_path -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1 -DZEND_SIGNALS)
  PHP_ADD_EXTENSION_DEP(pdo_taos, pdo)
fi
