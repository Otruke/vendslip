import { create } from 'zustand'
import { immer } from 'zustand/middleware/immer'
import { applyParse, draftToSlash, emptyDraft, type DraftItem, type DraftPayload } from '@/lib/draft'
import { parseSlash } from '@/lib/slash'

/**
 * Live /slash composer state. Typing in the command bar re-derives the draft synchronously
 * (pure parser, no network) so the paper-slip preview updates within a frame.
 * The structured form edits the same draft directly.
 */
type Mode = 'slash' | 'form'

type ComposerState = {
  mode: Mode
  text: string
  draft: DraftPayload
  errors: string[]
  templateId: number | null
  useCredit: boolean
  setMode: (mode: Mode) => void
  setText: (text: string) => void
  appendShortcut: (shortcut: string) => void
  update: (patch: Partial<DraftPayload>) => void
  setItem: (index: number, patch: Partial<DraftItem>) => void
  addItem: (item?: Partial<DraftItem>) => void
  removeItem: (index: number) => void
  setTemplateId: (id: number | null) => void
  setUseCredit: (value: boolean) => void
  reset: () => void
}

function derive(text: string) {
  return applyParse(emptyDraft(), parseSlash(text))
}

export const useComposerStore = create<ComposerState>()(
  immer((set) => ({
    mode: 'slash',
    text: '',
    draft: emptyDraft(),
    errors: [],
    templateId: null,
    useCredit: false,
    setMode: (mode) =>
      set((s) => {
        if (mode === 'slash' && s.mode === 'form') {
          // Keep both views in sync: serialise the structured draft back into /slash text.
          s.text = draftToSlash(s.draft)
          const { draft, errors } = derive(s.text)
          s.draft = { ...draft, items: draft.items.map((it, i) => ({ ...it, sku_id: s.draft.items[i]?.sku_id ?? null })) }
          s.errors = errors
        }
        s.mode = mode
      }),
    setText: (text) =>
      set((s) => {
        const { draft, errors } = derive(text)
        s.text = text
        s.draft = draft
        s.errors = errors
      }),
    appendShortcut: (shortcut) =>
      set((s) => {
        const sep = s.text === '' || s.text.endsWith('\n') ? '' : '\n'
        s.text = `${s.text}${sep}${shortcut}`
        const { draft, errors } = derive(s.text)
        s.draft = draft
        s.errors = errors
      }),
    // Form edits switch the source of truth to the structured draft (text is kept for reference).
    update: (patch) => set((s) => { Object.assign(s.draft, patch) }),
    setItem: (index, patch) => set((s) => { const item = s.draft.items[index]; if (item) Object.assign(item, patch) }),
    addItem: (item) => set((s) => { s.draft.items.push({ description: '', quantity: 1, unit_price_minor: null, ...item }) }),
    removeItem: (index) => set((s) => { s.draft.items.splice(index, 1) }),
    setTemplateId: (id) => set((s) => { s.templateId = id }),
    setUseCredit: (value) => set((s) => { s.useCredit = value }),
    reset: () => set((s) => { s.text = ''; s.draft = emptyDraft(); s.errors = []; s.useCredit = false }),
  })),
)
