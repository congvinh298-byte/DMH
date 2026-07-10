import sys
sys.stdout.reconfigure(encoding='utf-8')
with open('index.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
keywords = ['wheelModal', 'wheel-modal', 'wheelCanvas', 'spinBtn', 'wheelSpinsCount', 'wheelPrize', 'quay', 'Vong Quay', 'vong-quay', 'lucky_spin']
for i, line in enumerate(lines):
    if any(k in line for k in keywords):
        print(f'{i+1}: {line.rstrip()}')
