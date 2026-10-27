  import {
  Add,
  Menu,
  ArrowLeft2,
  ArrowRight2,
  // ArrowDown,
  Bank,
  Call,
  Clock,
  Convertshape,
  DocumentText,
  EmptyWallet,
  Gallery,
  Heart,
  Home2,
  Element3,
  Judge,
  Location,
  Login,
  Magicpen,
  MagicStar,
  Moneys,
  Profile2User,
  Profile,
  NotificationBing,
  SearchNormal1,
  SearchStatus,
  Send2,
  Setting4,
  Sort,
  Star1,
  TickCircle,
  ArrowDown2,
  UserSearch,
  ProfileTick,
  VolumeHigh,
  ArrowLeft,
  Card,
  Camera,
  Crown,
  DollarCircle,
  Monitor,
  Game,
  Ranking,
} from 'iconsax-react';
import { linear, type IconProps } from './types';

export function MenuIcon({ size = 24 }: IconProps) {
  return <Menu size={size} {...linear} />;
}

export function StarIcon({ size = 12 }: IconProps) {
  return <Star1 size={size} variant="Bold" color="currentColor" />;
}

export function GalleryIcon({ size = 28 }: IconProps) {
  return <Gallery size={size} {...linear} />;
}

export function LocationIcon({ size = 16 }: IconProps) {
  return <Location size={size} {...linear} />;
}

export function BankIcon({ size = 24 }: IconProps) {
  return <Bank size={size} {...linear} />;
}

export function MoneyIcon({ size = 16 }: IconProps) {
  return <Moneys size={size} {...linear} />;
}

export function TickCircleIcon({ size = 16 }: IconProps) {
  return <TickCircle size={size} {...linear} />;
}

export function SendIcon({ size = 20 }: IconProps) {
  return <Send2 size={size} {...linear} />;
}

export function PeopleIcon({ size = 16 }: IconProps) {
  return <Profile2User size={size} {...linear} />;
}

export function ScaleIcon({ size = 20 }: IconProps) {
  return <Judge size={size} {...linear} />;
}

export function CallIcon({ size = 20 }: IconProps) {
  return <Call size={size} {...linear} />;
}

export function ClockIcon({ size = 16 }: IconProps) {
  return <Clock size={size} {...linear} />;
}

export function LoginIcon({ size = 24 }: IconProps) {
  return <Login size={size} {...linear} />;
}

export function ConvertshapeIcon({ size = 24 }: IconProps) {
  return <Convertshape size={size} {...linear} />;
}

export function DocumentTextIcon({ size = 24 }: IconProps) {
  return <DocumentText size={size} {...linear} />;
}

export function Element3Icon({ size = 24, color = 'currentColor' }: IconProps & { color?: string }) {
  return <Element3 size={size} {...linear} color={color} />;
}

export function BrainIcon({ size = 24 }: IconProps) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden
    >
      <path
        d="M9.5 4.5c-1.8 0-3.2 1.3-3.4 3-.9.4-1.6 1.3-1.6 2.4 0 .6.2 1.1.5 1.5-.5.5-.8 1.2-.8 2 0 1.2.8 2.2 1.9 2.5-.1.3-.1.6-.1.8 0 1.7 1.4 3.1 3.1 3.1.5 0 1-.1 1.4-.4.5.8 1.4 1.3 2.4 1.3 1.1 0 2.1-.7 2.5-1.6.4.2.8.3 1.3.3 1.7 0 3.1-1.4 3.1-3.1 0-.3 0-.6-.1-.8 1.1-.3 1.9-1.3 1.9-2.5 0-.8-.3-1.5-.8-2 .3-.4.5-.9.5-1.5 0-1.1-.7-2-1.6-2.4-.2-1.7-1.6-3-3.4-3-1 0-1.9.4-2.5 1.1C11.4 4.9 10.5 4.5 9.5 4.5Z"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
      <path
        d="M12 5.5v13.2M9.2 8.2c.8.4 1.6.4 2.3 0M14.8 8.2c-.8.4-1.6.4-2.3 0M8.8 12.2h2.4M12.8 12.2h2.4M9.5 16h2"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function SearchNormalIcon({ size = 24 }: IconProps) {
  return <SearchNormal1 size={size} {...linear} />;
}

export function Home2Icon({ size = 24, color = 'currentColor' }: IconProps & { color?: string }) {
  return <Home2 size={size} {...linear} color={color} />;
}

export function MagicpenIcon({ size = 24 }: IconProps) {
  return <Magicpen size={size} {...linear} />;
}

export function MagicStarIcon({ size = 20 }: IconProps) {
  return <MagicStar size={size} {...linear} />;
}

export function EmptyWalletIcon({ size = 24 }: IconProps) {
  return <EmptyWallet size={size} {...linear} />;
}

export function AddIcon({ size = 24, className }: IconProps & { className?: string }) {
  return (
    <Add
      size={size}
      variant="Linear"
      color="#FFFFFF"
      className={className}
    />
  );
}

export function HeartIcon({ size = 20, color = 'currentColor' }: IconProps & { color?: string }) {
  return <Heart size={size} {...linear} color={color} />;
}
export function ArrowLeftIcon({ size = 24 }: IconProps) {
  return <ArrowLeft size={size} {...linear} />;
}
export function ArrowLeftIcon2({ size = 24 }: IconProps) {
  return <ArrowLeft2 size={size} {...linear} />;
}
export function ArrowRightIcon2({ size = 24 }: IconProps) {
  return <ArrowRight2 size={size} {...linear} />;
}

export function ProfileIcon({ size = 24, color = 'currentColor' }: IconProps & { color?: string }) {
  return <Profile size={size} {...linear} color={color} />;
}

export function NotificationBingIcon({
  size = 24,
  variant = 'Linear',
  color = 'currentColor',
}: IconProps & {
  variant?: 'Linear' | 'Outline' | 'Broken' | 'Bold' | 'Bulk' | 'TwoTone';
  color?: string;
}) {
  return <NotificationBing size={size} variant={variant} color={color} />;
}

export function ArrowDownIcon({ size = 24 }: IconProps) {
  return <ArrowDown2 size={size} {...linear} />;
} 

export function Setting4Icon({ size = 20 }: IconProps) {
  return <Setting4 size={size} {...linear} />;
}

export function SortIcon({ size = 20 }: IconProps) {
  return <Sort size={size} {...linear} />;
}

export function VolumeHighIcon({ size = 20 }: IconProps) {
  return <VolumeHigh size={size} {...linear} />;
}

export function SearchStatusIcon({ size = 20 }: IconProps) {
  return <SearchStatus size={size} {...linear} />;
}

export function UserSearchIcon({ size = 20 }: IconProps) {
  return <UserSearch size={size} {...linear} />;
}

export function ProfileTickIcon({ size = 20 }: IconProps) {
  return <ProfileTick size={size} {...linear} />;
}

export function CardIcon({ size = 20 }: IconProps) {
  return <Card size={size} {...linear} />;
}

export function CameraIcon({ size = 20 }: IconProps) {
  return <Camera size={size} {...linear} />;
}

export function MonitorIcon({ size = 20 }: IconProps) {
  return <Monitor size={size} {...linear} />;
}

export function DollarCircleIcon({ size = 20 }: IconProps) {
  return <DollarCircle size={size} {...linear} />;
}

export function CrownIcon({ size = 20 }: IconProps) {
  return <Crown size={size} {...linear} />;
}

export function GameIcon({ size = 24, color = 'currentColor' }: IconProps & { color?: string }) {
  return <Game size={size} {...linear} color={color} />;
}

export function RankingIcon({ size = 16 }: IconProps) {
  return <Ranking size={size} {...linear} />;
}