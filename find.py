import os

repo_dir = r'C:\Users\Kylian\Documents\seo-blog-affiliate\resources\views'

for root, dirs, files in os.walk(repo_dir):
    for file in files:
        if file.endswith('.blade.php'):
            path = os.path.join(root, file)
            try:
                with open(path, 'r', encoding='utf-8') as f:
                    for i, line in enumerate(f):
                        if '??' in line or '?????' in line or 'hp-author-avatar' in line or 'meta-icon' in line:
                            print(f"{file}:{i+1}: {line.strip()}")
            except Exception as e:
                pass
