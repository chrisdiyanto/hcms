import sys
import os
import json

# Ensure UTF-8 output on Windows
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

def main():
    if len(sys.argv) < 2:
        print("Usage: python convert_to_md.py <file_path>")
        sys.exit(1)

    file_path = sys.argv[1]

    if not os.path.exists(file_path):
        print(f"Error: File not found: {file_path}", file=sys.stderr)
        sys.exit(1)

    try:
        from markitdown import MarkItDown

        md = MarkItDown()
        result = md.convert(file_path)

        if result and result.text_content:
            print(result.text_content)
        else:
            print(f"Warning: Document produced empty content", file=sys.stderr)
            sys.exit(2)

    except ImportError:
        # Fallback if markitdown is not installed, attempt basic text reading
        ext = os.path.splitext(file_path)[1].lower()
        if ext in ['.txt', '.md', '.json', '.html', '.csv', '.xml']:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                print(f.read())
        else:
            print("Error: 'markitdown' library is not installed in Python environment. Run 'pip install markitdown' first.", file=sys.stderr)
            sys.exit(3)
    except Exception as e:
        print(f"Error converting document: {str(e)}", file=sys.stderr)
        sys.exit(4)

if __name__ == "__main__":
    main()
