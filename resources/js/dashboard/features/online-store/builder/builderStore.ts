import { create } from 'zustand';
import type { BuilderDevice, BuilderDocument, BuilderSection, SectionType } from '../types';
import { sectionRegistry } from './registry';

type BuilderHistory = {
  past: BuilderDocument[];
  future: BuilderDocument[];
};

type SaveStatus = 'idle' | 'dirty' | 'saving' | 'saved' | 'conflict' | 'error';

type BuilderState = {
  document: BuilderDocument | null;
  selectedNodeId: string | null;
  device: BuilderDevice;
  history: BuilderHistory;
  isDirty: boolean;
  saveStatus: SaveStatus;
  revision: number;
  setInitialDocument: (document: BuilderDocument, revision: number) => void;
  selectNode: (id: string | null) => void;
  setDevice: (device: BuilderDevice) => void;
  updateSectionSettings: (id: string, settings: Record<string, unknown>) => void;
  addSection: (type: SectionType) => void;
  addSectionAt: (type: SectionType, anchorId: string, position: 'before' | 'after') => void;
  removeSection: (id: string) => void;
  duplicateSection: (id: string) => void;
  toggleSection: (id: string) => void;
  reorderSections: (activeId: string, overId: string) => void;
  undo: () => void;
  redo: () => void;
  markSaving: () => void;
  markSaved: (revision: number) => void;
  markConflict: () => void;
  markError: () => void;
};

function cloneDocument(document: BuilderDocument): BuilderDocument {
  return structuredClone(document);
}

function commit(state: BuilderState, updater: (document: BuilderDocument) => BuilderDocument): Partial<BuilderState> {
  if (!state.document) return {};
  const next = updater(cloneDocument(state.document));
  return {
    document: next,
    history: { past: [...state.history.past, state.document].slice(-40), future: [] },
    isDirty: true,
    saveStatus: 'dirty',
  };
}

function freshId(prefix: string): string {
  return `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
}

function cloneSection(section: BuilderSection): BuilderSection {
  const next = structuredClone(section);
  next.id = freshId(section.type);
  next.locked = false;
  next.blocks = next.blocks?.map((block) => ({ ...block, id: freshId(block.type) }));
  return next;
}

export const useBuilderStore = create<BuilderState>((set) => ({
  document: null,
  selectedNodeId: null,
  device: 'desktop',
  history: { past: [], future: [] },
  isDirty: false,
  saveStatus: 'idle',
  revision: 1,
  setInitialDocument: (document, revision) => set({ document, revision, history: { past: [], future: [] }, isDirty: false, saveStatus: 'saved', selectedNodeId: document.sections[0]?.id ?? null }),
  selectNode: (id) => set({ selectedNodeId: id }),
  setDevice: (device) => set({ device }),
  updateSectionSettings: (id, settings) => set((state) => commit(state, (document) => ({ ...document, sections: document.sections.map((sectionItem) => sectionItem.id === id ? { ...sectionItem, settings: { ...sectionItem.settings, ...settings } } : sectionItem) }))),
  addSection: (type) => set((state) => commit(state, (document) => {
    const definition = sectionRegistry[type];
    const next = { ...structuredClone(definition.defaults), id: freshId(type) };
    return { ...document, sections: [...document.sections, next] };
  })),
  addSectionAt: (type, anchorId, position) => set((state) => commit(state, (document) => {
    const definition = sectionRegistry[type];
    const next = { ...structuredClone(definition.defaults), id: freshId(type) };
    const index = document.sections.findIndex((sectionItem) => sectionItem.id === anchorId);
    if (index < 0) return { ...document, sections: [...document.sections, next] };
    const sections = [...document.sections];
    sections.splice(position === 'before' ? index : index + 1, 0, next);
    return { ...document, sections };
  })),
  removeSection: (id) => set((state) => commit(state, (document) => ({ ...document, sections: document.sections.filter((sectionItem) => sectionItem.id !== id || sectionItem.locked) }))),
  duplicateSection: (id) => set((state) => commit(state, (document) => {
    const index = document.sections.findIndex((sectionItem) => sectionItem.id === id);
    if (index < 0) return document;
    const sections = [...document.sections];
    sections.splice(index + 1, 0, cloneSection(sections[index]));
    return { ...document, sections };
  })),
  toggleSection: (id) => set((state) => commit(state, (document) => ({ ...document, sections: document.sections.map((sectionItem) => sectionItem.id === id ? { ...sectionItem, enabled: !sectionItem.enabled } : sectionItem) }))),
  reorderSections: (activeId, overId) => set((state) => commit(state, (document) => {
    const from = document.sections.findIndex((sectionItem) => sectionItem.id === activeId);
    const to = document.sections.findIndex((sectionItem) => sectionItem.id === overId);
    if (from < 0 || to < 0) return document;
    const sections = [...document.sections];
    const [moved] = sections.splice(from, 1);
    sections.splice(to, 0, moved);
    return { ...document, sections };
  })),
  undo: () => set((state) => {
    const previous = state.history.past[state.history.past.length - 1];
    if (!previous || !state.document) return {};
    return { document: previous, history: { past: state.history.past.slice(0, -1), future: [state.document, ...state.history.future] }, isDirty: true, saveStatus: 'dirty' };
  }),
  redo: () => set((state) => {
    const next = state.history.future[0];
    if (!next || !state.document) return {};
    return { document: next, history: { past: [...state.history.past, state.document], future: state.history.future.slice(1) }, isDirty: true, saveStatus: 'dirty' };
  }),
  markSaving: () => set({ saveStatus: 'saving' }),
  markSaved: (revision) => set({ revision, isDirty: false, saveStatus: 'saved' }),
  markConflict: () => set({ saveStatus: 'conflict' }),
  markError: () => set({ saveStatus: 'error' }),
}));
