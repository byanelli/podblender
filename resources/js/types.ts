export type Metadata = {
    id: string,
    title: string,
    description: string,
    sourceId: string,
    sourceName: string,
};

export type PlatformType = {
    name: string;
};

export type MetadataResponse = {
    metadata: Metadata;
    platformType: PlatformType;
};

export type AudioSource = {
    name: string,
    platform_type: PlatformType,
};

export type AudioClip = {
    id: number,
    title: string,
    processing: boolean,
    audio_source: AudioSource,
}

export type Feed = {
    id: number,
    uuid: string,
    name: string,
    description: string,
    audio_clips: AudioClip[],
}
