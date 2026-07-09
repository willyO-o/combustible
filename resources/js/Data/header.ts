import us_flag from "@/Assets/images/flags/us_flag.jpg"
import spain_flag from "@/Assets/images/flags/spain_flag.jpg"
import french_flag from "@/Assets/images/flags/french_flag.jpg"
import uae_flag from "@/Assets/images/flags/uae_flag.jpg"
import germany_flag from "@/Assets/images/flags/germany_flag.jpg"
import china_flag from "@/Assets/images/flags/china_flag.jpg"
import italy_flag from "@/Assets/images/flags/italy_flag.jpg"
import russia_flag from "@/Assets/images/flags/russia_flag.jpg"
import e_13 from "@/Assets/images/ecommerce/png/13.png"
import e_15 from "@/Assets/images/ecommerce/png/15.png"
import e_19 from "@/Assets/images/ecommerce/png/19.png"
import e_11 from "@/Assets/images/ecommerce/png/11.png"
import e_6 from "@/Assets/images/ecommerce/png/6.png"
import face1 from "@/Assets/images/faces/1.jpg";
import face12 from "@/Assets/images/faces/12.jpg";

interface LanguageType {
    name: string;
    flag: string;
}

interface NotificationNoteType {
    id: number;
    image: string;
    name: string;
    description: string;
    oldprice: string;
    newprice: string;
    off: string;
    liclass?: string;
    quantity: number
}

interface NotificationItem {
    id: number;
    title: string;
    description: string;
    time: string;
    icon?: string;
    avatar?: string;
    isUnread?: boolean;
    liClass?: string;
}

export let
    Languages: LanguageType[] = [
        { name: 'English', flag: us_flag },
        { name: 'Spanish', flag: spain_flag },
        { name: 'français', flag: french_flag },
        { name: 'عربي', flag: uae_flag },
        { name: 'Deutsch', flag: germany_flag },
        { name: '中国人', flag: china_flag },
        { name: 'Italiano', flag: italy_flag },
        { name: 'Русский', flag: russia_flag },
    ],
    notificationNotes: NotificationNoteType[] = [
        { id: 1, quantity: 1, image: e_13, name: "Urban Chic Satchel", description: "Sleek, stylish, and perfect for daily use", oldprice: '$120', newprice: '$90', off: '25%' },
        { id: 2, quantity: 1, image: e_15, name: "TrailBlaze Runners", description: "Lightweight and built for comfort", oldprice: '$80', newprice: '$60', off: '25%' },
        { id: 3, quantity: 1, image: e_19, name: "VisionTech SLR", description: "High-quality shots with every click", oldprice: '$500', newprice: '$375', off: '25%' },
        { id: 4, quantity: 5, image: e_6, name: "FlexiSeat Office Chair", description: "Comfortable support for long hours", oldprice: '$200', newprice: '$150', off: '25%' },
        { id: 5, quantity: 2, image: e_11, name: "DecoDial Classic", description: "A bold, colorful timepiece for any room", oldprice: '$50', newprice: '$35', off: '30%', liclass: "border-bottom-0" },
    ],

    Notifications: NotificationItem[] = [
        {
            id: 1,
            title: "New Message",
            description: "You have received a new message from John Doe",
            time: "11:45am",
            avatar: face1,
            isUnread: false
        },
        {
            id: 2,
            title: "Task Reminder",
            description: "Don't forget to submit your report by 3 PM today",
            time: "02:16pm",
            icon: "ri-notification-line",
            isUnread: false
        },
        {
            id: 3,
            title: "Friend Request",
            description: "Jane Smith sent you a friend request",
            time: "10:04am",
            avatar: face12,
            isUnread: true
        },
        {
            id: 4,
            title: "Event Reminder",
            description: "You have an upcoming event: Team Meeting on October 25 at 10 AM.",
            time: "12:58pm",
            icon: "ri-notification-line",
            isUnread: true
        },
        {
            id: 5,
            title: "File Uploaded",
            description: 'The file "Project_Proposal.pdf" has been uploaded successfully',
            time: "05:13pm",
            icon: "ri-notification-line",
            isUnread: true,
            liClass: 'border-bottom-0'
        }
    ];
