try:
    import bcrypt
    h = b"$2b$10$13u6qwCOwODv34GyCMgdWub6oQF3RX0rG7c3d3X4JvzuEmAXLYDd2"
    print("bcrypt valid:", bcrypt.checkpw(b"pfsense", h))
except Exception as e:
    print(e)
