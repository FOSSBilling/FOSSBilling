"""Offline SQLite transport for the native PHP service SQL (no reimplemented policy)."""
import json
import sqlite3
import sys

request = json.load(sys.stdin)
with sqlite3.connect(sys.argv[1]) as connection:
    connection.row_factory = sqlite3.Row
    cursor = connection.execute(request["sql"], request["params"])
    rows = [dict(row) for row in cursor.fetchall()] if cursor.description else []
    print(json.dumps({"rows": rows, "rowcount": cursor.rowcount}))
