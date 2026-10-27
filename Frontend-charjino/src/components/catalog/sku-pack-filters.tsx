"use client";

import { Chip, LocationIcon } from "@/components/ui";
import { SelectField } from "@/components/ui/field";
import { optionLabel } from "@/lib/sku-pack-filters";
import type { PackFilter } from "@/types/catalog";

const SELECT_THRESHOLD = 10;

type SkuPackFiltersProps = {
  filters: PackFilter[];
  value: Record<string, string>;
  onChange: (key: string, next: string) => void;
};

export function SkuPackFilters({
  filters,
  value,
  onChange,
}: SkuPackFiltersProps) {
  if (filters.length === 0) return null;

  return (
    <div className="flex flex-col gap-3">
      {filters.map((filter) => {
        const selected = value[filter.key] ?? "";
        const useSelect = filter.options.length > SELECT_THRESHOLD;
        return (
          <div key={filter.key} className="flex min-w-0 flex-col gap-2">
            {useSelect ? null : (
              <p className="text-sm text-text/70">{filter.label}</p>
            )}
            {useSelect ? (
              <SelectField
                variant="shop"
                compact
                searchable
                showItemIcon={false}
                label={filter.label}
                placeholder="همه"
                searchPlaceholder={`جستجوی ${filter.label}`}
                icon={
                  filter.key === "region" ? (
                    <LocationIcon size={18} />
                  ) : undefined
                }
                options={[
                  { value: "", label: "همه" },
                  ...filter.options.map((option) => ({
                    value: option.value,
                    label: optionLabel(filter, option),
                  })),
                ]}
                value={selected}
                onChange={(next) => onChange(filter.key, next)}
              />
            ) : (
              <div className="grid grid-cols-3 min-w-0 items-center gap-2.5 overflow-x-auto pb-0.5 ">
                <Chip
                  appearance="filter"
                  className="w-full py-4 rounded-sm shadow-sm"
                  label="همه"
                  selected={!selected}
                  onClick={() => onChange(filter.key, "")}
                />
                {filter.options.map((option) => (
                  <Chip
                    key={option.value}
                    className="w-full py-4 rounded-sm shadow-sm"
                    appearance="filter"
                    label={optionLabel(filter, option)}
                    selected={
                      selected.toLowerCase() === option.value.toLowerCase()
                    }
                    onClick={() =>
                      onChange(
                        filter.key,
                        selected.toLowerCase() === option.value.toLowerCase()
                          ? ""
                          : option.value,
                      )
                    }
                  />
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
