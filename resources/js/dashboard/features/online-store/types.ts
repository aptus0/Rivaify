export type BuilderDevice = 'desktop' | 'tablet' | 'mobile';

export type SectionType =
  | 'header'
  | 'announcement'
  | 'hero'
  | 'product-grid'
  | 'product-carousel'
  | 'featured-product'
  | 'category-showcase'
  | 'collection-grid'
  | 'collection-carousel'
  | 'image-banner'
  | 'image-with-text'
  | 'video'
  | 'rich-text'
  | 'reviews'
  | 'faq'
  | 'countdown'
  | 'promotion-tiles'
  | 'newsletter'
  | 'instagram-feed'
  | 'logo-list'
  | 'trust-badges'
  | 'spacer'
  | 'footer';

export type BuilderBlock = {
  id: string;
  type: string;
  settings: Record<string, unknown>;
};

export type BuilderSection = {
  id: string;
  type: SectionType;
  enabled: boolean;
  locked?: boolean;
  settings: Record<string, unknown>;
  blocks?: BuilderBlock[];
};

export type BuilderDocument = {
  version: 1;
  template: string;
  sections: BuilderSection[];
};

export type ThemeTokens = {
  colors: Record<string, string>;
  typography: {
    heading_font: string;
    body_font: string;
    heading_scale: string;
    body_scale: string;
  };
  radius: Record<string, string>;
};

export type StoreTheme = {
  id: string;
  name: string;
  base_key: string;
  base_version: string;
  release_version?: string | null;
  source?: 'official' | 'custom_upload' | string;
  trust_level?: 'official' | 'verified' | 'custom' | string;
  publisher?: string | null;
  engine?: string | null;
  status: string;
  tokens: ThemeTokens;
  published_at: string | null;
  published_version: number | null;
};

export type ThemePackageStage = {
  key: string;
  label: string;
  status: 'pending' | 'passed' | 'warning' | 'failed';
};

export type ThemePackageIssue = {
  stage: string;
  message: string;
  file?: string;
  line?: number;
  column?: number;
  suggestion?: string | null;
};

export type ThemePackageReport = {
  status: 'passed' | 'failed' | 'processing';
  stages: ThemePackageStage[];
  errors: ThemePackageIssue[];
  warnings: ThemePackageIssue[];
  summary: {
    templates?: number;
    sections?: number;
    languages?: number;
    assets?: number;
  };
};

export type ThemePackageInspection = {
  id: string;
  status: 'quarantined' | 'validated' | 'failed' | 'installed';
  filename: string;
  size_bytes: number;
  sha256: string;
  manifest: {
    id?: string;
    name?: string;
    version?: string;
    engine?: string;
    author?: { name?: string; namespace?: string };
    templates?: string[];
    sections?: unknown[];
  } | null;
  report: ThemePackageReport | null;
};

export type BuilderDocumentRecord = {
  id: string;
  resource_type: string;
  resource_key: string;
  schema_version: number;
  revision: number;
  document: BuilderDocument;
  updated_at: string | null;
};

export type OnlineStoreOverview = {
  store: {
    id: string;
    name: string;
    slug: string;
    status: 'draft' | 'live';
    domain: string;
    storefront_url: string;
  };
  summary: {
    theme: string;
    domain_status: 'connected' | 'pending';
    last_publish: string | null;
    pages: number;
    menus: number;
  };
  theme: StoreTheme;
  document: BuilderDocumentRecord | null;
};

export type VersionHistoryItem = {
  id: string;
  version: number;
  status: string;
  published_at: string | null;
  created_at: string | null;
};
