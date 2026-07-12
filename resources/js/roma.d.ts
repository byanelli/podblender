// This file is auto-generated. Do not edit by hand.

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
  platformType: PlatformTypeResponse;
}

export interface PlatformTypeResponse {
  name: string;
  value: number;
}

export interface SourceMetadata {
  name: string;
  canonicalUrl: string;
}
