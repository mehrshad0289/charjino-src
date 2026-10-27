export type ProfilePersonal = {
  firstName: string;
  lastName: string;
  role: string;
  location: string;
  email: string;
  phone: string;
  bio: string;
  avatar: string;
  facebook: string;
  twitter: string;
  linkedin: string;
  instagram: string;
};

export type ProfileAddress = {
  country: string;
  city: string;
  postalCode: string;
  taxId: string;
};

export type ProfileUser = {
  personal: ProfilePersonal;
  address: ProfileAddress;
};
