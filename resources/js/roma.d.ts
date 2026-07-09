// This file is auto-generated. Do not edit by hand.

export interface AudioClipUrlRequestBody {
  url: string;
}

export interface ClipMetadataResponse {
  title: string;
  description: string;
  canonicalUrl: string;
  publishedAt: string;
  source: SourceMetadataResponse;
}

export interface CreateCustomFeedRequestBody {
  name: string;
}

export interface CreateSubscriptionRequestBody {
  url: string;
  name: string;
}

export interface MetadataResponseBody {
  metadata: ClipMetadataResponse;
  platformType: PlatformTypeResponse;
}

export interface PlatformTypeResponse {
  name: string;
  value: number;
}

export interface SourceMetadataResponse {
  name: string;
  canonicalUrl: string;
}
