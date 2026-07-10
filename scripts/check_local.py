import sys
sys.stdout.reconfigure(encoding='utf-8')
with open('index.php', 'r', encoding='utf-8') as f:
    content = f.read()
idx = content.rfind('async function spinWheel')
if idx != -1:
    print('Local spinWheel found at char:', idx)
    area = content[idx:idx+100]
    print('Context:', repr(area))
    # Check wheelModal
    search = 'id="wheelModal"'
    wm = content.find(search)
    if wm != -1:
        print('wheelModal at char:', wm)
        print('Context:', repr(content[wm:wm+100]))
    else:
        print('wheelModal NOT in local index.php')
