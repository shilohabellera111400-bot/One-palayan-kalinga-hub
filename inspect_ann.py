with open('admin/manage-residents.php', 'r', encoding='utf-8') as f:
    text = f.read()

pos = text.find('function openModal()')
print(text[pos:pos+400])
