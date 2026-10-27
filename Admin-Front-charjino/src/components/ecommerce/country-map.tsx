'use client';

import { IRAN_FOCUS_REGIONS, IRAN_REGION_NAMES } from '@/data/iran-map';
import { iranMill } from '@react-jvectormap/iran';
import dynamic from 'next/dynamic';
import '@react-jvectormap/lib/jquery-jvectormap.css';

const VectorMap = dynamic(() => import('@react-jvectormap/core').then((mod) => mod.VectorMap), {
  ssr: false
});

type CountryMapProps = {
  mapColor?: string;
};

type RegionTipElement = {
  html?: (value: string) => void;
};

export function CountryMap({ mapColor }: CountryMapProps) {
  return (
    <VectorMap
      map={iranMill}
      backgroundColor='transparent'
      selectedRegions={[...IRAN_FOCUS_REGIONS]}
      zoomOnScroll={false}
      zoomMax={12}
      zoomMin={1}
      zoomAnimate
      zoomStep={1.5}
      regionStyle={{
        initial: {
          fill: mapColor || '#D0D5DD',
          fillOpacity: 1,
          fontFamily: 'Yekan Bakh',
          stroke: 'none',
          strokeWidth: 0,
          strokeOpacity: 0
        },
        hover: { fillOpacity: 0.7, cursor: 'pointer', fill: '#465fff', stroke: 'none' },
        selected: { fill: '#465FFF' },
        selectedHover: {}
      }}
      regionLabelStyle={{
        initial: { fill: '#35373e', fontWeight: 500, fontSize: '13px', stroke: 'none' },
        hover: {},
        selected: {},
        selectedHover: {}
      }}
      onRegionTipShow={(_event, el, code) => {
        const name = IRAN_REGION_NAMES[code];
        const tip = el as RegionTipElement;
        if (name && typeof tip.html === 'function') tip.html(name);
      }}
    />
  );
}
