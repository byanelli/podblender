export type Metadata = {
    id: string,
    title: string,
    description: string,
    sourceId: string,
    sourceName: string,
};

export type ClipMetadata = {
    title: string,
    description: string,
    canonicalUrl: string,
    source: SourceMetadata,
};

export type SourceMetadata = {
    name: string,
    canonicalUrl: string,
};

export type PlatformType = {
    name: string;
};

export type ClipProcessingState = {
    name: 'Processing' | 'Processed' | 'Unavailable';
};

export type ClipMetadataResponse = {
    metadata: ClipMetadata;
    platformType: PlatformType;
};

export type SourceMetadataResponse = {
    metadata: SourceMetadata;
    platformType: PlatformType;
};

export type AudioSource = {
    name: string,
    platform_type: PlatformType,
};

export type AudioClip = {
    id: number,
    title: string,
    processing_state: ClipProcessingState,
    audio_source: AudioSource,
    platform_url: string,
    created_at: string,
    published_at: string,

}

export type Feed = {
    id: number,
    uuid: string,
    name: string,
    description: string,
    audio_clips: AudioClip[],
    subscription: AudioSource|null,
}
