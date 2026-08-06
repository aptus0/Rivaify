import { useEffect } from 'react';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/react';
import { Bold, Heading2, Italic, Link2, List, ListOrdered } from 'lucide-react';

function ToolButton({
  label,
  active = false,
  onClick,
  children,
}: {
  label: string;
  active?: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      className={`grid h-8 w-8 place-items-center rounded text-sm transition ${
        active ? 'bg-surface-orange text-primary-hover' : 'text-muted hover:bg-app-bg hover:text-dark'
      }`}
    >
      {children}
    </button>
  );
}

export function RichTextEditor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        link: {
          openOnClick: false,
          autolink: true,
          protocols: ['http', 'https'],
          HTMLAttributes: { rel: 'nofollow noopener noreferrer' },
        },
      }),
    ],
    content: value,
    editorProps: {
      attributes: {
        class: 'min-h-40 px-3 py-3 text-sm leading-6 text-dark outline-none',
      },
    },
    onUpdate: ({ editor: currentEditor }) => onChange(currentEditor.getHTML()),
  });

  useEffect(() => {
    if (editor && value !== editor.getHTML()) {
      editor.commands.setContent(value, { emitUpdate: false });
    }
  }, [editor, value]);

  if (!editor) {
    return <div className="min-h-40 animate-pulse rounded-md border border-border bg-app-bg" />;
  }

  function addLink() {
    const href = window.prompt('Bağlantı adresi');
    if (!href) return;
    editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
  }

  return (
    <div className="overflow-hidden rounded-md border border-border bg-card focus-within:border-primary">
      <div className="flex flex-wrap items-center gap-1 border-b border-border bg-app-bg p-1.5">
        <ToolButton label="Kalın" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}><Bold size={16} /></ToolButton>
        <ToolButton label="İtalik" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}><Italic size={16} /></ToolButton>
        <ToolButton label="Başlık" active={editor.isActive('heading', { level: 2 })} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}><Heading2 size={16} /></ToolButton>
        <ToolButton label="Madde listesi" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()}><List size={16} /></ToolButton>
        <ToolButton label="Numaralı liste" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()}><ListOrdered size={16} /></ToolButton>
        <ToolButton label="Bağlantı ekle" active={editor.isActive('link')} onClick={addLink}><Link2 size={16} /></ToolButton>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}