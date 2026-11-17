with open('C:/Users/shiloh/.gemini/antigravity/brain/6dcd8e23-2d67-47b2-8f3d-00e8ebdb103b/.system_generated/logs/transcript_full.jsonl', 'r', encoding='utf-8') as f:
    for line in f:
        if '"step_index":5449' in line:
            import json
            obj = json.loads(line)
            for tc in obj.get('tool_calls', []):
                args = tc.get('args', {})
                r = args.get('Replacement', '') or args.get('Content', '')
                with open('scratch/modal_extracted.html', 'w', encoding='utf-8') as out:
                    out.write(r)
            print("WROTE MODAL")
