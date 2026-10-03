import sqlite3
con = sqlite3.connect('c:/pfsense/installer_source/var/db/pkg/local.sqlite')
cur = con.cursor()
rows = cur.execute('SELECT name, version FROM packages WHERE name LIKE "%kernel%" OR name LIKE "%pfSense%"').fetchall()
for r in rows:
    print(r)
