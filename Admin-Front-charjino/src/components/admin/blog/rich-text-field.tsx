'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import CharacterCount from '@tiptap/extension-character-count';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import Youtube from '@tiptap/extension-youtube';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import DOMPurify from 'isomorphic-dompurify';
import { searchBlogLinks, uploadBlogImage } from '@/actions/blog.actions';
import { Button, Checkbox, Field, Modal, TextField } from '@/components/ui';
import type { FieldState } from '@/components/ui';
import type { BlogLinkHit } from '@/types/blog';
import styles from './rich-text-field.module.css';

const PASTE_TAGS = [
  'h2',
  'h3',
  'h4',
  'p',
  'br',
  'strong',
  'b',
  'em',
  'i',
  'u',
  'ul',
  'ol',
  'li',
  'blockquote',
  'a',
  'img',
  'table',
  'thead',
  'tbody',
  'tr',
  'th',
  'td'
];

type RichTextFieldProps = {
  label: string;
  value: string;
  onChange: (html: string) => void;
  onBlur?: () => void;
  hint?: string;
  previewState?: FieldState;
};

type ImageDraft = { file: File; preview: string };
type LinkDraft = { href: string; blank: boolean; nofollow: boolean };

export function RichTextField({ label, value, onChange, onBlur, hint, previewState }: RichTextFieldProps) {
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;
  const [htmlMode, setHtmlMode] = useState(false);
  const [imageDraft, setImageDraft] = useState<ImageDraft | null>(null);
  const [imageAlt, setImageAlt] = useState('');
  const [linkOpen, setLinkOpen] = useState(false);
  const [linkDraft, setLinkDraft] = useState<LinkDraft>({ href: '', blank: false, nofollow: false });
  const [linkQ, setLinkQ] = useState('');
  const [hits, setHits] = useState<{ posts: BlogLinkHit[]; products: BlogLinkHit[] }>({ posts: [], products: [] });
  const [busy, setBusy] = useState(false);

  const editorRef = useRef<ReturnType<typeof useEditor>>(null);

  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
      Underline,
      Link.configure({
        openOnClick: false,
        autolink: true,
        protocols: ['http', 'https'],
        HTMLAttributes: { rel: null },
        validate: (href) =>
          (href.startsWith('/') && !href.startsWith('//')) ||
          /^https:\/\//i.test(href)
      }),
      Image.configure({ inline: false, allowBase64: false }),
      Table.configure({ resizable: false }),
      TableRow,
      TableHeader,
      TableCell,
      Placeholder.configure({ placeholder: 'متن مطلب را بنویس…' }),
      CharacterCount,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      Youtube.configure({ nocookie: true, width: 640, height: 360 })
    ],
    content: value || '<p></p>',
    editorProps: {
      attributes: { dir: 'rtl', lang: 'fa' },
      handleKeyDown(_view, event) {
        if (event.key !== 'Enter' || event.shiftKey) return false;
        const current = editorRef.current;
        if (!current) return false;
        if (![2, 3, 4].some((level) => current.isActive('heading', { level }))) return false;
        const { $from } = current.state.selection;
        if ($from.parentOffset !== $from.parent.content.size) return false;
        return current.chain().splitBlock().setParagraph().run();
      },
      transformPastedHTML(html) {
        return DOMPurify.sanitize(html, {
          ALLOWED_TAGS: PASTE_TAGS,
          ALLOWED_ATTR: ['href', 'src', 'alt', 'width', 'height', 'rel', 'target', 'colspan', 'rowspan'],
          FORBID_TAGS: ['style', 'script', 'h1'],
          FORBID_ATTR: ['style', 'class', 'onerror', 'onclick']
        });
      }
    },
    onUpdate({ editor: current }) {
      onChangeRef.current(current.getHTML());
    },
    onBlur() {
      onBlur?.();
    }
  });
  editorRef.current = editor;

  useEffect(() => {
    if (!editor) return;
    const next = value || '<p></p>';
    if (editor.getHTML() !== next) {
      editor.commands.setContent(next, false);
    }
  }, [editor, value]);

  const insertImageFile = useCallback((file: File) => {
    if (!file.type.startsWith('image/')) return;
    setImageAlt('');
    setImageDraft({ file, preview: URL.createObjectURL(file) });
  }, []);

  useEffect(() => {
    if (!editor) return;
    const dom = editor.view.dom;
    function onPaste(event: ClipboardEvent) {
      const file = event.clipboardData?.files?.[0];
      if (file?.type.startsWith('image/')) {
        event.preventDefault();
        insertImageFile(file);
      }
    }
    function onDrop(event: DragEvent) {
      const file = event.dataTransfer?.files?.[0];
      if (file?.type.startsWith('image/')) {
        event.preventDefault();
        insertImageFile(file);
      }
    }
    dom.addEventListener('paste', onPaste);
    dom.addEventListener('drop', onDrop);
    return () => {
      dom.removeEventListener('paste', onPaste);
      dom.removeEventListener('drop', onDrop);
    };
  }, [editor, insertImageFile]);

  async function confirmImage() {
    if (!imageDraft || !editor) return;
    const alt = imageAlt.trim();
    if (!alt) return;
    setBusy(true);
    const formData = new FormData();
    formData.append('file', imageDraft.file);
    formData.append('alt', alt);
    formData.append('role', 'inline');
    const result = await uploadBlogImage(formData);
    setBusy(false);
    if (!result.ok) return;
    const width = result.width ? ` width="${result.width}"` : '';
    const height = result.height ? ` height="${result.height}"` : '';
    editor
      .chain()
      .focus()
      .insertContent(`<img src="${result.url}" alt="${alt.replace(/"/g, '&quot;')}"${width}${height}>`)
      .run();
    URL.revokeObjectURL(imageDraft.preview);
    setImageDraft(null);
  }

  async function lookupLinks(q: string) {
    setLinkQ(q);
    if (q.trim().length < 2) {
      setHits({ posts: [], products: [] });
      return;
    }
    const result = await searchBlogLinks(q.trim());
    if (result.ok) setHits({ posts: result.posts, products: result.products });
  }

  function applyLink() {
    if (!editor) return;
    const href = linkDraft.href.trim();
    if (!href || href.startsWith('javascript:') || href.startsWith('data:')) return;
    const rel: string[] = [];
    if (linkDraft.nofollow) rel.push('nofollow');
    if (!href.startsWith('/')) rel.push('noopener', 'noreferrer');
    editor.chain().focus().extendMarkRange('link').setLink({
      href,
      target: linkDraft.blank ? '_blank' : null,
      rel: rel.length ? rel.join(' ') : null
    }).run();
    setLinkOpen(false);
  }

  function tool(labelText: string, active: boolean, onClick: () => void) {
    return (
      <button type='button' className={styles.tool} data-active={active ? 'true' : 'false'} onClick={onClick}>
        {labelText}
      </button>
    );
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div className={styles.wrap} dir='rtl'>
        <div className={styles.toolbar}>
          {tool('H2', Boolean(editor?.isActive('heading', { level: 2 })), () => editor?.chain().focus().toggleHeading({ level: 2 }).run())}
          {tool('H3', Boolean(editor?.isActive('heading', { level: 3 })), () => editor?.chain().focus().toggleHeading({ level: 3 }).run())}
          {tool('H4', Boolean(editor?.isActive('heading', { level: 4 })), () => editor?.chain().focus().toggleHeading({ level: 4 }).run())}
          {tool('ضخیم', Boolean(editor?.isActive('bold')), () => editor?.chain().focus().toggleBold().run())}
          {tool('ایتالیک', Boolean(editor?.isActive('italic')), () => editor?.chain().focus().toggleItalic().run())}
          {tool('زیرخط', Boolean(editor?.isActive('underline')), () => editor?.chain().focus().toggleUnderline().run())}
          {tool('فهرست', Boolean(editor?.isActive('bulletList')), () => editor?.chain().focus().toggleBulletList().run())}
          {tool('شماره', Boolean(editor?.isActive('orderedList')), () => editor?.chain().focus().toggleOrderedList().run())}
          {tool('نقل‌قول', Boolean(editor?.isActive('blockquote')), () => editor?.chain().focus().toggleBlockquote().run())}
          {tool('لینک', Boolean(editor?.isActive('link')), () => {
            setLinkDraft({
              href: String(editor?.getAttributes('link').href || ''),
              blank: editor?.getAttributes('link').target === '_blank',
              nofollow: String(editor?.getAttributes('link').rel || '').includes('nofollow')
            });
            setLinkOpen(true);
          })}
          <label className={styles.tool}>
            تصویر
            <input
              type='file'
              accept='image/png,image/jpeg,image/webp'
              hidden
              onChange={(event) => {
                const file = event.target.files?.[0];
                if (file) insertImageFile(file);
                event.target.value = '';
              }}
            />
          </label>
          {tool('جدول', false, () => editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())}
          {tool('راست', Boolean(editor?.isActive({ textAlign: 'right' })), () => editor?.chain().focus().setTextAlign('right').run())}
          {tool('وسط', Boolean(editor?.isActive({ textAlign: 'center' })), () => editor?.chain().focus().setTextAlign('center').run())}
          {tool('چپ', Boolean(editor?.isActive({ textAlign: 'left' })), () => editor?.chain().focus().setTextAlign('left').run())}
          {tool('بازگشت', false, () => editor?.chain().focus().undo().run())}
          {tool('بعد', false, () => editor?.chain().focus().redo().run())}
          {tool(htmlMode ? 'ویرایشگر' : 'HTML', htmlMode, () => setHtmlMode((v) => !v))}
        </div>
        {htmlMode ? (
          <textarea
            className={styles.html}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            onBlur={onBlur}
          />
        ) : (
          <div className={styles.surface}>
            <EditorContent editor={editor} />
          </div>
        )}
      </div>

      <Modal
        isOpen={Boolean(imageDraft)}
        onClose={() => setImageDraft(null)}
        className='mx-4 w-full max-w-[560px]'
      >
        <div className={styles.modal}>
          <h2 className='text-base font-semibold text-gray-800 dark:text-white'>درج تصویر</h2>
          <p className='text-sm text-gray-600 dark:text-gray-300'>متن جایگزین تصویر الزامی است</p>
          {imageDraft ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={imageDraft.preview} alt='' className='max-h-40 rounded-xl object-contain' />
          ) : null}
          <TextField id='blog-image-alt' label='متن جایگزین (alt)' value={imageAlt} onChange={setImageAlt} />
          <div className='flex flex-wrap gap-2'>
            <Button type='button' loading={busy} disabled={!imageAlt.trim()} onClick={() => void confirmImage()}>
              درج تصویر
            </Button>
            <Button type='button' variant='outline' onClick={() => setImageDraft(null)}>
              انصراف
            </Button>
          </div>
        </div>
      </Modal>

      <Modal isOpen={linkOpen} onClose={() => setLinkOpen(false)} className='mx-4 w-full max-w-[560px]'>
        <div className={styles.modal}>
          <h2 className='text-base font-semibold text-gray-800 dark:text-white'>درج لینک</h2>
          <TextField label='آدرس لینک' value={linkDraft.href} onChange={(href) => setLinkDraft((d) => ({ ...d, href }))} />
          <Checkbox label='تب جدید' checked={linkDraft.blank} onChange={(blank) => setLinkDraft((d) => ({ ...d, blank }))} />
          <Checkbox
            label='nofollow'
            checked={linkDraft.nofollow}
            onChange={(nofollow) => setLinkDraft((d) => ({ ...d, nofollow }))}
          />
          <TextField label='جستجوی مطلب یا محصول' value={linkQ} onChange={(q) => void lookupLinks(q)} />
          <div className={styles.hits}>
            {[...hits.posts, ...hits.products].map((hit) => (
              <button
                key={hit.href}
                type='button'
                className={styles.hit}
                onClick={() => setLinkDraft((d) => ({ ...d, href: hit.href }))}
              >
                {hit.title}
              </button>
            ))}
          </div>
          <div className='flex flex-wrap gap-2'>
            <Button type='button' onClick={applyLink}>
              درج لینک
            </Button>
            <Button type='button' variant='outline' onClick={() => setLinkOpen(false)}>
              انصراف
            </Button>
          </div>
        </div>
      </Modal>
    </Field>
  );
}
