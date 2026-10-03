import sqlite3
con = sqlite3.connect('c:/pfsense/installer_source/var/db/pkg/local.sqlite')
cur = con.cursor()
rows = cur.execute('SELECT path FROM files WHERE path LIKE "%upgrade%"').fetchall()
for r in rows:
    print(r)
