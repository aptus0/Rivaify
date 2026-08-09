import { z } from 'zod';

export const builderSectionSchema = z.object({
  id: z.string().min(1),
  type: z.enum([
    'header',
    'announcement',
    'hero',
    'product-grid',
    'product-carousel',
    'featured-product',
    'category-showcase',
    'collection-grid',
    'collection-carousel',
    'image-banner',
    'image-with-text',
    'video',
    'rich-text',
    'reviews',
    'faq',
    'countdown',
    'promotion-tiles',
    'newsletter',
    'instagram-feed',
    'logo-list',
    'trust-badges',
    'spacer',
    'footer',
  ]),
  enabled: z.boolean(),
  locked: z.boolean().optional(),
  settings: z.record(z.string(), z.unknown()),
  blocks: z.array(z.object({ id: z.string(), type: z.string(), settings: z.record(z.string(), z.unknown()) })).optional(),
});

export const builderDocumentSchema = z.object({
  version: z.literal(1),
  template: z.string().min(1),
  sections: z.array(builderSectionSchema),
});
