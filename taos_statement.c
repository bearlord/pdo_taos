#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include <stdio.h>
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

struct {
    int64_t long_value;
    int64_t ts_value;
    uint64_t ulong_value;
    int32_t int_value;
    uint32_t uint_value;
    int16_t small_value;
    uint16_t usmall_value;
    int8_t tiny_value;
    uint8_t utiny_value;
    float float_value;
    double double_value;
    char binary_value[10];
    char nchar_value[32];
} v = {0};


/* }}} */

static int pdo_pdo_taos_stmt_execute_prepared(pdo_stmt_t *stmt) /* {{{ */
{
    pdo_taos_stmt *S = stmt->driver_data;
    pdo_taos_db_handle *H = S->H;
    TAOS_RES *result;
    zend_long row_count;
    int i;

    taos_stmt_bind_param(S->stmt, S->params);
    if (S->params) {
        memset(S->params, 0, S->num_params * sizeof(TAOS_BIND));
    }

    int code = taos_stmt_execute(S->stmt);
    if (code != 0) {
        pdo_taos_error_stmt_msg(stmt, pdo_taos_convert_errno(code), taos_stmt_errstr(S->stmt));
        return 0;
    }

    if (!S->result) {
        S->result = taos_stmt_use_result(S->stmt);
        if (S->result) {
            S->fields = taos_fetch_fields(S->result);

            if (S->bound_result) {
                int j;
                for (j = 0; j < stmt->column_count; j++) {
                    efree(S->bound_result[j].buffer);
                }
                efree(S->bound_result);
                efree(S->out_null);
                efree(S->out_length);
            }

            stmt->column_count = (int) taos_field_count(S->result);

            S->bound_result = ecalloc(stmt->column_count, sizeof(TAOS_BIND));
            S->out_null = ecalloc(stmt->column_count, sizeof(zend_bool));
            S->out_length = ecalloc(stmt->column_count, sizeof(zend_ulong));

            /* summon memory to hold the row */
            for (i = 0; i < stmt->column_count; i++) {
                switch (S->fields[i].type) {
                    case TSDB_DATA_TYPE_NULL:
                    case TSDB_DATA_TYPE_BOOL:
                    case TSDB_DATA_TYPE_TINYINT:
                        S->bound_result[i].buffer_length = sizeof(v.tiny_value);
                        break;
                    case TSDB_DATA_TYPE_UTINYINT:
                        S->bound_result[i].buffer_length = sizeof(v.utiny_value);
                        break;
                    case TSDB_DATA_TYPE_SMALLINT:
                    case TSDB_DATA_TYPE_USMALLINT:
                        S->bound_result[i].buffer_length = sizeof(v.usmall_value);
                        break;
                    case TSDB_DATA_TYPE_INT:
                        S->bound_result[i].buffer_length = sizeof(v.int_value);
                        break;
                    case TSDB_DATA_TYPE_FLOAT:
                    case TSDB_DATA_TYPE_UINT:
                        S->bound_result[i].buffer_length = sizeof(v.uint_value);
                        break;
                    case TSDB_DATA_TYPE_BIGINT:
                        S->bound_result[i].buffer_length = sizeof(v.long_value);
                        break;
                    case TSDB_DATA_TYPE_DOUBLE:
                        S->bound_result[i].buffer_length = sizeof(v.double_value);
                        break;
                    case TSDB_DATA_TYPE_TIMESTAMP:
                        S->bound_result[i].buffer_length = sizeof(v.ts_value);
                        break;
                    case TSDB_DATA_TYPE_UBIGINT:
                        S->bound_result[i].buffer_length = sizeof(v.ulong_value);
                        break;
                    case TSDB_DATA_TYPE_BINARY:
                        S->bound_result[i].buffer_length = sizeof(v.binary_value);
                        break;
                    case TSDB_DATA_TYPE_NCHAR:
                        S->bound_result[i].buffer_length = sizeof(v.nchar_value);
                        break;
                }

                S->out_length[i] = S->bound_result[i].buffer_length;
//                S->out_length[i] =  0;
                S->bound_result[i].buffer = emalloc(S->bound_result[i].buffer_length);
                S->bound_result[i].is_null = &S->out_null[i];
                S->bound_result[i].length = &S->out_length[i];
                S->bound_result[i].buffer_type = TSDB_DATA_TYPE_BINARY;
            }
        }

    }

    return 1;
}

/* }}} */

static int pdo_taos_stmt_dtor(pdo_stmt_t *stmt) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    pdo_taos_db_handle *H = S->H;

    if (S->result) {
        /* free the resource */
        taos_free_result(S->result);
        S->result = NULL;
    }

    if (S->einfo.errmsg) {
        pefree(S->einfo.errmsg, stmt->dbh->is_persistent);
        S->einfo.errmsg = NULL;
    }

    if (S->stmt) {
        taos_stmt_close(S->stmt);
        S->stmt = NULL;
    }

    if (S->params) {
        efree(S->params);
    }
    if (S->in_null) {
        efree(S->in_null);
    }
    if (S->in_length) {
        efree(S->in_length);
    }

    if (S->bound_result)
    {
        int i;
        for (i = 0; i < stmt->column_count; i++) {
            efree(S->bound_result[i].buffer);
        }

        efree(S->bound_result);
        efree(S->out_null);
        efree(S->out_length);
    }

    efree(S);

    return 1;
}

static int pdo_taos_stmt_execute(pdo_stmt_t *stmt) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    pdo_taos_db_handle *H = S->H;

    //to do modify
    if (S->stmt) {
        return pdo_pdo_taos_stmt_execute_prepared(stmt);
    }

    if (S->result) {
        taos_free_result(S->result);
        S->result = NULL;
    }

    S->result = taos_query(H->server, stmt->active_query_string);

    stmt->row_count = (zend_long) taos_affected_rows(S->result);
    stmt->column_count = (int) taos_num_fields(S->result);
    S->fields = taos_fetch_fields(S->result);

    return 1;
}

static const char *const pdo_param_event_names[] =
        {
                "PDO_PARAM_EVT_ALLOC",
                "PDO_PARAM_EVT_FREE",
                "PDO_PARAM_EVT_EXEC_PRE",
                "PDO_PARAM_EVT_EXEC_POST",
                "PDO_PARAM_EVT_FETCH_PRE",
                "PDO_PARAM_EVT_FETCH_POST",
                "PDO_PARAM_EVT_NORMALIZE"
        };

static int
pdo_taos_stmt_param_hook(pdo_stmt_t *stmt, struct pdo_bound_param_data *param, enum pdo_param_event event_type) {
    zval *parameter;
    TAOS_BIND *b;
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;

    if (S->stmt && param->is_param) {
        switch (event_type) {
            case PDO_PARAM_EVT_ALLOC:
                /* sanity check parameter number range */
                if (param->paramno < 0 || param->paramno >= S->num_params) {
                    strcpy(stmt->error_code, "HY093");
                    return 0;
                }
                S->params_given++;

                b = &S->params[param->paramno];
                param->driver_data = b;
                b->is_null = &S->in_null[param->paramno];
                b->length = &S->in_length[param->paramno];

                return 1;

            case PDO_PARAM_EVT_EXEC_PRE:
                if (S->params_given < (unsigned int) S->num_params) {
                    /* too few parameter bound */
                    strcpy(stmt->error_code, "HY093");
                    return 0;
                }

                if (!Z_ISREF(param->parameter)) {
                    parameter = &param->parameter;
                } else {
                    parameter = Z_REFVAL(param->parameter);
                }

                b = (PDO_TAOS_PARAM_BIND *) param->driver_data;
                *b->is_null = 0;
                if (PDO_PARAM_TYPE(param->param_type) == PDO_PARAM_NULL || Z_TYPE_P(parameter) == IS_NULL) {
                    *b->is_null = 1;
                    b->buffer_type = TSDB_DATA_TYPE_BINARY;
                    b->buffer = NULL;
                    b->buffer_length = 0;
                    *b->length = 0;
                    return 1;
                }

                switch (PDO_PARAM_TYPE(param->param_type)) {
                    case PDO_PARAM_STMT:
                        return 0;

                    case PDO_PARAM_LOB:
                        if (!Z_ISREF(param->parameter)) {
                            parameter = &param->parameter;
                        } else {
                            parameter = Z_REFVAL(param->parameter);
                        }
                        if (Z_TYPE_P(parameter) == IS_RESOURCE) {
                            php_stream *stm = NULL;
                            php_stream_from_zval_no_verify(stm, parameter);
                            if (stm) {
                                zend_string *mem = php_stream_copy_to_mem(stm, PHP_STREAM_COPY_ALL, 0);
                                zval_ptr_dtor(parameter);
                                ZVAL_STR(parameter, mem ? mem : ZSTR_EMPTY_ALLOC());
                            } else {
                                pdo_raise_impl_error(stmt->dbh, stmt, "HY105", "Expected a stream resource");
                                return 0;
                            }
                        }
                        /* fall through */

                    default:;
                }

                if (!Z_ISREF(param->parameter)) {
                    parameter = &param->parameter;
                } else {
                    parameter = Z_REFVAL(param->parameter);
                }
                switch (Z_TYPE_P(parameter)) {
                    case IS_STRING:
                        b->buffer_type = TSDB_DATA_TYPE_BINARY;
                        b->buffer = Z_STRVAL_P(parameter);
                        b->buffer_length = Z_STRLEN_P(parameter);
                        *b->length = Z_STRLEN_P(parameter);
                        return 1;

                    case IS_LONG:
                        b->buffer_type = TSDB_DATA_TYPE_BIGINT;
                        b->buffer = &Z_LVAL_P(parameter);
                        return 1;

                    case IS_DOUBLE:
                        b->buffer_type = TSDB_DATA_TYPE_DOUBLE;
                        b->buffer = &Z_DVAL_P(parameter);
                        return 1;

                    default:
                        return 0;
                }


            case PDO_PARAM_EVT_FREE:
            case PDO_PARAM_EVT_EXEC_POST:
            case PDO_PARAM_EVT_FETCH_PRE:
            case PDO_PARAM_EVT_FETCH_POST:
            case PDO_PARAM_EVT_NORMALIZE:
                /* do nothing */
                break;
        }
    }

    return 1;
}

static int pdo_taos_stmt_fetch(pdo_stmt_t *stmt, enum pdo_fetch_orientation ori, long offset) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    pdo_taos_db_handle *H = S->H;
    TAOS_RES *result;

    if (!S->stmt) {
        return 0;
    }

    if ((S->current_data = taos_fetch_row(S->result)) == NULL) {
        return 0;
    }

    S->current_lengths = taos_fetch_lengths(S->result);

    return 1;
}

static int pdo_taos_stmt_describe(pdo_stmt_t *stmt, int colno) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    struct pdo_column_data *cols = stmt->columns;

    if (!S->result) {
        return 0;
    }

    if (colno >= stmt->column_count) {
        /* error invalid column */
        return 0;
    }

    /* fetch all on demand, this seems easiest
    ** if we've been here before bail out
    */
    if (cols[0].name) {
        return 1;
    }

    int i;
    for (i = 0; i < stmt->column_count; i++) {
        cols[i].name = zend_string_init(S->fields[i].name, strlen(S->fields[i].name), 0);
        cols[i].precision = 0;
        cols[i].maxlen = S->fields[i].bytes;
        cols[i].param_type = PDO_PARAM_STR;
    }
    return 1;
}

static int pdo_taos_stmt_get_col(pdo_stmt_t *stmt, int colno, char **ptr, unsigned long *len, int *caller_frees) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    if (!S->result) {
        return 0;
    }

    if (!S->stmt) {
        if (S->current_data == NULL || !S->result) {
            return 0;
        }
    }

    if (colno >= stmt->column_count) {
        return 0;
    }

    if (S->stmt) {
        char value[S->out_length[colno]];
        TAOS_ROW row = S->current_data;

        switch (S->fields[colno].type) {
            case TSDB_DATA_TYPE_NULL:
                break;
            case TSDB_DATA_TYPE_BOOL:
                sprintf(value, "%s", ((((int32_t)(*((char *) row[colno]))) == 1) ? "1" : "0"));
                *ptr = value;
                *len = S->out_length[colno];
                break;
            case TSDB_DATA_TYPE_TINYINT:
                sprintf(value, "%d", *((int8_t *) row[colno]));
                *len = strlen(value);
                *ptr = value;
                break;
            case TSDB_DATA_TYPE_UTINYINT:
                sprintf(value, "%u", *((uint8_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_SMALLINT:
                sprintf(value, "%d", *((int16_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_USMALLINT:
                sprintf(value, "%u", *((uint16_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_INT:
                sprintf(value, "%d", *((int32_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_FLOAT:
                sprintf(value, "%.5f", *(float *) row[colno]);
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_UINT:
                sprintf(value, "%u", *((uint32_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_BIGINT:
                sprintf(value, "%"
                PRId64, *((int64_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_DOUBLE:
                sprintf(value, "%.9lf", *((uint64_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;
            case TSDB_DATA_TYPE_TIMESTAMP: {
                int32_t precision;
                time_t tt;
                struct tm *tp;
                char timeStr[30] = {0};

                precision = taos_result_precision(S->result);
                if (precision == 0) {
                    tt = (*(int64_t *) row[colno]) / 1000;
                } else if (precision == 1) {
                    tt = (*(int64_t *) row[colno]) / 1000000;
                } else {
                    tt = (*(int64_t *) row[colno]) / 1000000000;
                }

                tp = localtime(&tt);
                strftime(timeStr, 64, "%y-%m-%d %H:%M:%S", tp);
                if (precision == 0) {
                    sprintf(value, "%s.%03d", timeStr, (int32_t)(*((int64_t *) row[colno]) % 1000));
                } else if (precision == 1) {
                    sprintf(value, "%s.%06d", timeStr, (int32_t)(*((int64_t *) row[colno]) % 1000000));
                } else {
                    sprintf(value, "%s.%09d", timeStr, (int32_t)(*((int64_t *) row[colno]) % 1000000000));
                }

                *ptr = value;
                *len = strlen(value);
            }
                break;
            case TSDB_DATA_TYPE_UBIGINT:
                sprintf(value, "%"
                PRIu64, *((uint64_t *) row[colno]));
                *ptr = value;
                *len = strlen(value);
                break;

            case TSDB_DATA_TYPE_BINARY:
            case TSDB_DATA_TYPE_NCHAR: {
                int32_t charLen;
                charLen = varDataLen((char *) row[colno] - VARSTR_HEADER_SIZE);
                *ptr = (char *) row[colno];
                *len = charLen;
            }
                break;
        }

        if (S->out_length[colno] > S->bound_result[colno].buffer_length) {
            strcpy(stmt->error_code, "01004"); /* truncated */
            S->out_length[colno] = S->bound_result[colno].buffer_length;
            *len = S->out_length[colno];
            return 0;
        }

        return 1;
    }

    *ptr = S->current_data[colno];
    *len = S->current_lengths[colno];

    return 1;
}

static int pdo_taos_stmt_get_column_meta(pdo_stmt_t *stmt, long colno, zval *return_value) {
    pdo_taos_stmt *S = (pdo_taos_stmt *) stmt->driver_data;
    const TAOS_FIELD *F;
    zval flags;


    if (!S->result) {
        return FAILURE;
    }
    if (colno >= stmt->column_count) {
        /* error invalid column */
        return FAILURE;
    }

    array_init(return_value);
    array_init(&flags);

    add_assoc_zval(return_value, "flags", &flags);

    return SUCCESS;
}

static int pdo_taos_stmt_cursor_closer(pdo_stmt_t *stmt) {
    printf("pdo_taos_stmt_cursor_closer\n");
    return 1;
}

const struct pdo_stmt_methods taos_stmt_methods = {
        pdo_taos_stmt_dtor,
        pdo_taos_stmt_execute,
        pdo_taos_stmt_fetch,
        pdo_taos_stmt_describe,
        pdo_taos_stmt_get_col,
        pdo_taos_stmt_param_hook,
        NULL, /* set_attr */
        NULL, /* get_attr */
        pdo_taos_stmt_get_column_meta,
        NULL,  /* next_rowset */
        NULL /* cursor_closer */
};
