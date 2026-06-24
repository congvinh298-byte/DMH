with open('admin_xxx.php', 'rb') as f:
    content = f.read()
text = content.decode('utf-8-sig')
text = text.replace('placeholder="qltmdt@moit.gov.vn"', 'placeholder="Nhập email admin"')
text = text.replace('\n            <p class="note">Tài khoản test: qltmdt@moit.gov.vn / Admin@123</p>\n', '\n')
with open('admin_xxx.php', 'wb') as f:
    f.write(text.encode('utf-8'))
print('Done')
