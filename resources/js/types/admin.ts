export interface AdminUser {
    name: string;
    email: string;
    avatar: string | null;
    google_id: string;
    role: 'admin' | 'staff';
}

export interface Booking {
    id: number;
    studentName: string;
    studentId: string;
    studentType: string;
    zone: string;
    roomName: string;
    date: string;
    timeSlot: string;
    status: "pending" | "approved" | "rejected";
}

export interface Room {
    id: number;
    name: string;
    zone: string;
    active: boolean;
}

export interface Member {
    id: number;
    name: string;
    email: string;
    code: string;
    type: string;
    faculty: string;
    totalBookings: number;
    lastBooking: string;
}

export interface Holiday {
    id: number;
    date: string;
    name: string;
    type: "national" | "library";
}

export interface ServiceDay {
    day: string;
    isOpen: boolean;
    openTime: string;
    closeTime: string;
}

export interface Feedback {
    id: number;
    studentInfo: string;
    stars: number;
    comment: string;
}
