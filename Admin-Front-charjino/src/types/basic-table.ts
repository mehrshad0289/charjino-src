export type BasicTableStatus = 'active' | 'pending' | 'cancel';

export type BasicTableRow = {
  id: number;
  user: {
    image: string;
    name: string;
    role: string;
  };
  projectName: string;
  team: {
    images: string[];
  };
  status: BasicTableStatus;
  budget: string;
};
