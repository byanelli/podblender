// This file is auto-generated. Do not edit by hand.

export const AudioSourceType = {
  Channel: { name: 'Channel', value: 'channel' },
  Playlist: { name: 'Playlist', value: 'playlist' },
} as const;

export type AudioSourceType = typeof AudioSourceType[keyof typeof AudioSourceType];

export const PlatformTypeEnum = {
  YouTube: { name: 'YouTube', value: 1 },
  Web: { name: 'Web', value: 2 },
  Rss: { name: 'Rss', value: 3 },
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
  estimatedDownloadTime: number | null;
}

export interface CreateCustomFeedRequestBody {
  name: string;
}

export interface CreateSubscriptionRequestBody {
  url: string;
  name: string;
  backfillSince?: string | null;
  tracksNewEpisodes?: boolean;
}

export interface MetadataResponseBody {
  metadata: ClipMetadata;
  platformType: PlatformTypeEnum;
}

export interface SourceMetadata {
  name: string;
  canonicalUrl: string;
  authorName: string;
  type: AudioSourceType;
  clipCount: number | null;
}

export interface SourceMetadataResponseBody {
  metadata: SourceMetadata;
  platformType: PlatformTypeEnum;
}
