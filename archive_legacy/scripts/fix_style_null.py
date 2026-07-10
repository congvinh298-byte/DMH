import re

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Instead of complex regex, let's just make it simple. We will find lines and replace them carefully.
def replace_style_assignment(match):
    var_name = match.group(1)
    prop = match.group(2)
    val = match.group(3)
    # If it's already inside an if, don't double wrap
    return f'if ({var_name}) {var_name}.style.{prop} = {val}'

# regex to find:   var_name.style.property = 'value';
# We only want to replace assignments that are alone on a line or simple statements
# It's better to manually replace the specific lines identified to avoid breaking JS syntax (like inside loops or ternary)

replacements = [
    (r"modal\.style\.display = 'block';", r"if(modal) modal.style.display = 'block';"),
    (r"qrImg\.style\.display = 'inline-block';", r"if(qrImg) qrImg.style.display = 'inline-block';"),
    (r"qrImg\.style\.display = 'none';", r"if(qrImg) qrImg.style.display = 'none';"),
    (r"panel\.style\.display = 'block';", r"if(panel) panel.style.display = 'block';"),
    (r"panel\.style\.display = 'none';", r"if(panel) panel.style.display = 'none';"),
    (r"statusEl\.style\.display = 'none';", r"if(statusEl) statusEl.style.display = 'none';"),
    (r"statusEl\.style\.display = 'block';", r"if(statusEl) statusEl.style.display = 'block';"),
    (r"statusEl\.style\.background = '#f1f5f9';", r"if(statusEl) statusEl.style.background = '#f1f5f9';"),
    (r"statusEl\.style\.border = '1px solid #cbd5e1';", r"if(statusEl) statusEl.style.border = '1px solid #cbd5e1';"),
    (r"statusEl\.style\.background = '#f0fdf4';", r"if(statusEl) statusEl.style.background = '#f0fdf4';"),
    (r"statusEl\.style\.border = '1px solid #bbf7d0';", r"if(statusEl) statusEl.style.border = '1px solid #bbf7d0';"),
    (r"statusEl\.style\.background = '#fef2f2';", r"if(statusEl) statusEl.style.background = '#fef2f2';"),
    (r"statusEl\.style\.border = '1px solid #fecaca';", r"if(statusEl) statusEl.style.border = '1px solid #fecaca';"),
    (r"serviceSelectorPanel\.style\.display = 'block';", r"if(serviceSelectorPanel) serviceSelectorPanel.style.display = 'block';"),
    (r"serviceSelectorPanel\.style\.display = 'none';", r"if(serviceSelectorPanel) serviceSelectorPanel.style.display = 'none';"),
    (r"serviceModal\.style\.display = 'none';", r"if(serviceModal) serviceModal.style.display = 'none';"),
    (r"readerDiv\.style\.display = 'none';", r"if(readerDiv) readerDiv.style.display = 'none';"),
    (r"readerDiv\.style\.display = 'block';", r"if(readerDiv) readerDiv.style.display = 'block';"),
]

for old, new in replacements:
    content = re.sub(old, new, content)

with open('c:/Users/pcpv/OneDrive/Desktop/DTH/index.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Replaced successfully")
