#ifndef PHP_PDO_TAOS_INT_H
#define PHP_PDO_TAOS_INT_H

#include <taos.h>
#include <taoserror.h>
#include <php.h>

#define PDO_TAOS_PARAM_BIND TAOS_BIND
#define PHP_PDO_TAOS_CONNECTION_FAILURE_SQLSTATE "08006"

typedef struct {
    const char *file;
    int line;
    unsigned int errcode;
    char *errmsg;
} pdo_taos_error_info;

/* stuff we use in a taos database handle */
typedef struct {
    TAOS *server;
    unsigned attached: 1;
    pdo_taos_error_info einfo;
    unsigned int stmt_counter;
    zend_ulong max_buffer_size;
    unsigned fetch_table_names: 1;
} pdo_taos_db_handle;

typedef struct {
    TAOS_FIELD *def;
} pdo_taos_column;

typedef struct {
    pdo_taos_db_handle *H;
    TAOS_RES *result;
    TAOS_STMT *stmt;
    TAOS_FIELD *fields;
    TAOS_BIND *params;
    TAOS_BIND *bound_result;
    TAOS_ROW current_data;
    int *current_lengths;
    int num_params;
    int *in_null;
    zend_ulong *in_length;
    unsigned int params_given;
    unsigned max_length: 1;
    pdo_taos_error_info einfo;
    int current_row;
    pdo_taos_column *cols;
    char *query;
    char **param_values;
    int *param_lengths;
    int *param_formats;
    zend_bool is_prepared;
    int *out_null;
    zend_ulong *out_length;
} pdo_taos_stmt;

extern const pdo_driver_t pdo_taos_driver;

extern int _pdo_taos_error(pdo_dbh_t *dbh, pdo_stmt_t *stmt, int errcode, const char *sqlstate, const char *msg, const char *file,
                int line);

#define pdo_taos_error(s) _pdo_taos_error(s, NULL, 0, NULL, NULL, __FILE__, __LINE__)
#define pdo_taos_error_msg(s, e, m)    _pdo_taos_error(s, NULL, e, NULL, m, __FILE__, __LINE__)
#define pdo_taos_error_stmt(s, e, z)    _pdo_taos_error(s->dbh, s, e, z, NULL, __FILE__, __LINE__)
#define pdo_taos_error_stmt_msg(s, e, m)    _pdo_taos_error(s->dbh, s, e, NULL, m, __FILE__, __LINE__)

extern const struct pdo_stmt_methods taos_stmt_methods;

#define pdo_taos_sqlstate(r) (const char *)NULL

enum {
    PDO_TAOS_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT = PDO_ATTR_DRIVER_SPECIFIC,
};

/*
struct pdo_taos_lob_self {
    pdo_dbh_t *dbh;
    TAOS *conn;
    int lfd;
};
*/

php_stream *pdo_taos_create_lob_stream(zval *pdh, int lfd);

//extern const php_stream_ops pdo_taos_lob_stream_ops;

#endif /* PHP_PDO_TAOS_INT_H */