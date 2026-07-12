// This file is auto-generated. Do not edit by hand.

export const PlatformTypeEnum = {
  YouTube: { name: 'YouTube', value: 1 },
  Web: { name: 'Web', value: 2 },
} as const;

export type PlatformTypeEnum = typeof PlatformTypeEnum[keyof typeof PlatformTypeEnum];

export interface AudioClipUrlRequestBody {
  url: string;
}

export interface ClipMetadata {
  title: string;
  description: string;
  canonicalUrl: string;
  publishedAt: string;
  source: SourceMetadata;
}

export interface CreateCustomFeedRequestBody {
  name: string;
}

export interface CreateSubscriptionRequestBody {
  url: string;
  name: string;
}

export interface MetadataResponseBody {
  metadata: ClipMetadata;
  platformType: PlatformTypeEnum;
}

export interface SourceMetadata {
  name: string;
  canonicalUrl: string;
}
